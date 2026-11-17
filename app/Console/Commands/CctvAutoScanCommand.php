<?php

namespace App\Console\Commands;

use App\Models\Camera;
use App\Services\CameraStreamService;
use App\Services\SolatAttendanceService;
use App\Services\SolatSessionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Pemindai absensi wajah otomatis dari kamera CCTV di latar belakang (Headless Service).
 *
 * Perintah ini dapat dijalankan terus-menerus di server (via Supervisor, Systemd,
 * atau terminal) tanpa perlu ada petugas yang membuka halaman web. Sistem secara
 * cerdas memeriksa sesi solat, mengambil frame dari CCTV, dan mencatat absensi
 * jamaah yang melintas secara otomatis.
 */
class CctvAutoScanCommand extends Command
{
    public const CacheKey = 'cctv_worker_status';

    protected $signature = 'cctv:auto-scan
                            {--camera= : ID kamera tertentu yang ingin dipindai}
                            {--interval=2 : Jeda antar pemindaian dalam detik (default 2 detik)}
                            {--once : Jalankan hanya satu kali putaran untuk pengujian}';

    protected $description = 'Jalankan pemindaian absensi wajah otomatis dari CCTV di latar belakang tanpa peramban';

    /** @var array<int, int> */
    private array $failCounts = [];

    /** @var array<int, int> */
    private array $skipUntil = [];

    /** @var array<int, array<int, int>> */
    private array $prevMotionSig = [];

    /** @var array<int, int> */
    private array $burstUntil = [];

    /** @var array<int, int> */
    private array $lastAiScanAt = [];

    public function handle(
        CameraStreamService $streamService,
        SolatAttendanceService $attendanceService,
        SolatSessionService $sessionService
    ): int {
        $interval = max(1, (int) $this->option('interval'));
        $singleRun = (bool) $this->option('once');
        $specificCameraId = $this->option('camera');

        $this->info('====================================================');
        $this->info('  PEMINDAI CCTV OTOMATIS (HEADLESS BACKGROUND WORKER) ');
        $this->info('====================================================');
        $this->line('Jeda pemindaian: ' . $interval . ' detik per putaran.');
        if ($specificCameraId) {
            $this->line('Kamera target ID: ' . $specificCameraId);
        }
        $this->line('Tekan Ctrl+C untuk menghentikan pemindai.');
        $this->newLine();

        $running = true;

        // Tangani sinyal terminate jika didukung sistem operasi
        if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, function () use (&$running) {
                $running = false;
                $this->warn("\nMenerima sinyal berhenti. Mematikan worker...");
            });
            pcntl_signal(SIGTERM, function () use (&$running) {
                $running = false;
                $this->warn("\nMenerima sinyal berhenti. Mematikan worker...");
            });
        }

        while ($running) {
            // Perbarui status heartbeat di Cache agar dapat dipantau di halaman web /kamera
            Cache::put(self::CacheKey, [
                'running' => true,
                'last_beat' => now()->timestamp,
                'timestamp' => now()->toIso8601String(),
            ], 20);

            // 1. Ambil daftar kamera aktif
            $query = Camera::query()->active();
            if ($specificCameraId) {
                $query->where('id', $specificCameraId);
            }
            $cameras = $query->get();

            if ($cameras->isEmpty()) {
                $this->warn('[' . now()->format('H:i:s') . '] Belum ada kamera CCTV yang aktif.');
                if ($singleRun) {
                    break;
                }
                sleep(5);
                continue;
            }

            // 2. Periksa apakah ada sesi solat aktif atau sesi uji coba
            $activeSession = $sessionService->activeSession();
            $testSession = $sessionService->testSession();
            $isTestOpen = $testSession?->isOpen() ?? false;

            if ($activeSession === null && ! $isTestOpen) {
                $this->line('[' . now()->format('H:i:s') . '] Tidak ada sesi solat atau uji coba yang dibuka. Menunggu sesi...');
                if ($singleRun) {
                    break;
                }
                sleep(5);
                continue;
            }

            $sessionName = $activeSession ? $activeSession->prayerLabel() : 'Uji Coba';

            // 3. Pindai setiap kamera CCTV aktif (dengan jeda otomatis bagi kamera offline)
            $anyBurst = false;
            foreach ($cameras as $camera) {
                $camId = (int) $camera->id;
                if (isset($this->skipUntil[$camId]) && now()->timestamp < $this->skipUntil[$camId]) {
                    continue;
                }

                if ($this->scanCamera($camera, $sessionName, $streamService, $attendanceService)) {
                    $anyBurst = true;
                }
            }

            if ($singleRun) {
                break;
            }

            if ($anyBurst) {
                usleep(450000);
            } else {
                sleep($interval);
            }
        }

        Cache::forget(self::CacheKey);
        $this->info('Pemindai CCTV otomatis telah dinonaktifkan.');

        return self::SUCCESS;
    }

    protected function scanCamera(
        Camera $camera,
        string $sessionName,
        CameraStreamService $streamService,
        SolatAttendanceService $attendanceService
    ): bool {
        try {
            $camId = (int) $camera->id;
            $frame = $streamService->captureSnapshot($camera);

            if ($frame === null) {
                $fails = ($this->failCounts[$camId] ?? 0) + 1;
                $this->failCounts[$camId] = $fails;
                if ($fails >= 2) {
                    $backoffSeconds = min(60, 15 * ($fails - 1));
                    $this->skipUntil[$camId] = now()->timestamp + $backoffSeconds;
                    $this->warn('[' . now()->format('H:i:s') . "] [{$camera->name}] Gagal mengambil frame siaran (jeda {$backoffSeconds}d agar kamera lain tidak terhambat).");
                } else {
                    $this->warn('[' . now()->format('H:i:s') . "] [{$camera->name}] Gagal mengambil frame siaran.");
                }
                return false;
            }

            $this->failCounts[$camId] = 0;
            unset($this->skipUntil[$camId]);

            $nowTs = now()->timestamp;
            $hasMotion = $this->hasMotion($camId, $frame);
            if ($hasMotion) {
                $this->burstUntil[$camId] = max($this->burstUntil[$camId] ?? 0, $nowTs + 5);
            }

            $inBurst = $nowTs <= ($this->burstUntil[$camId] ?? 0);
            $needHeartbeat = ($nowTs - ($this->lastAiScanAt[$camId] ?? 0)) >= 4;

            if (! $inBurst && ! $needHeartbeat) {
                return false;
            }

            $this->lastAiScanAt[$camId] = $nowTs;
            $deviceId = 'cctv-' . $camera->id;
            $result = $attendanceService->recordFacesFromImage($frame, $deviceId, now(), allowTestSession: true);

            $faces = $result['faces'] ?? [];
            $count = count($faces);

            if ($count > 0) {
                $this->burstUntil[$camId] = now()->timestamp + 6;
                $inBurst = true;
            }

            Cache::put('cctv_last_scan_' . $camera->id, [
                'faces' => $faces,
                'width' => $result['width'] ?? 1920,
                'height' => $result['height'] ?? 1080,
                'timestamp' => now()->timestamp,
            ], 12);

            if ($count === 0) {
                $this->line('[' . now()->format('H:i:s') . "] [{$camera->name}] [Sesi {$sessionName}] Tidak ada wajah terdeteksi.");
                return $inBurst;
            }

            foreach ($faces as $face) {
                $memberName = $face['member']['name'] ?? ($face['label'] ?? 'Tidak dikenal');
                $status = $face['attendance']['status'] ?? $face['status'];
                $displayMsg = $face['attendance']['display_message'] ?? ($face['display_message'] ?? $status);
                $recorded = ! empty($face['attendance']['recorded']);

                if ($recorded) {
                    $this->info('[' . now()->format('H:i:s') . "] [{$camera->name}] HADIR: {$memberName} — {$displayMsg} (Sesi {$sessionName})");
                } elseif ($status === 'duplicate') {
                    $this->line('[' . now()->format('H:i:s') . "] [{$camera->name}] Terdeteksi: {$memberName} — {$displayMsg}");
                } else {
                    $this->line('[' . now()->format('H:i:s') . "] [{$camera->name}] Terdeteksi: {$memberName} — {$displayMsg}");
                }
            }

            return $inBurst;
        } catch (\Throwable $e) {
            $this->error('[' . now()->format('H:i:s') . "] [{$camera->name}] Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deteksi gerakan ringan (16x9 grayscale) agar CPU AI tidak bekerja saat koridor sepi.
     */
    protected function hasMotion(int $camId, string $frameBytes): bool
    {
        if (! function_exists('imagecreatefromstring')) {
            return true;
        }

        $src = @imagecreatefromstring($frameBytes);
        if ($src === false) {
            return true;
        }

        $thumb = imagecreatetruecolor(16, 9);
        if ($thumb === false) {
            imagedestroy($src);
            return true;
        }

        imagecopyresampled($thumb, $src, 0, 0, 0, 0, 16, 9, imagesx($src), imagesy($src));
        imagedestroy($src);

        $sig = [];
        for ($y = 0; $y < 9; $y++) {
            for ($x = 0; $x < 16; $x++) {
                $rgb = imagecolorat($thumb, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $sig[] = (int) (($r * 30 + $g * 59 + $b * 11) / 100);
            }
        }
        imagedestroy($thumb);

        $prev = $this->prevMotionSig[$camId] ?? null;
        $this->prevMotionSig[$camId] = $sig;

        if ($prev === null || count($prev) !== count($sig)) {
            return true;
        }

        $diff = 0;
        $total = count($sig);
        for ($i = 0; $i < $total; $i++) {
            $diff += abs($sig[$i] - $prev[$i]);
        }

        return ($diff / $total) >= 3.5;
    }
}

