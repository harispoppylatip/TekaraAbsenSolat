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

            // 3. Pindai setiap kamera CCTV aktif
            foreach ($cameras as $camera) {
                $this->scanCamera($camera, $sessionName, $streamService, $attendanceService);
            }

            if ($singleRun) {
                break;
            }

            sleep($interval);
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
    ): void {
        try {
            $frame = $streamService->captureSnapshot($camera);

            if ($frame === null) {
                $this->warn('[' . now()->format('H:i:s') . "] [{$camera->name}] Gagal mengambil frame siaran.");
                return;
            }

            $deviceId = 'cctv-' . $camera->id;
            $result = $attendanceService->recordFacesFromImage($frame, $deviceId, now(), allowTestSession: true);

            $faces = $result['faces'] ?? [];
            $count = count($faces);

            Cache::put('cctv_last_scan_' . $camera->id, [
                'faces' => $faces,
                'width' => $result['width'] ?? 1920,
                'height' => $result['height'] ?? 1080,
                'timestamp' => now()->timestamp,
            ], 12);

            if ($count === 0) {
                $this->line('[' . now()->format('H:i:s') . "] [{$camera->name}] [Sesi {$sessionName}] Tidak ada wajah terdeteksi.");
                return;
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
        } catch (\Throwable $e) {
            $this->error('[' . now()->format('H:i:s') . "] [{$camera->name}] Error: " . $e->getMessage());
        }
    }
}

