<?php

namespace App\Http\Controllers;

use App\Console\Commands\CctvAutoScanCommand;
use App\Models\Camera;
use App\Models\Setting;
use App\Models\SolatAttendanceLog;
use App\Services\FaceEncodingService;
use App\Services\SolatAttendanceService;
use App\Services\SolatSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class KioskController extends Controller
{
    /**
     * Tampilkan halaman Layar Monitor / Kiosk Masjid (/layar).
     */
    public function index(SolatSessionService $sessions, FaceEncodingService $encoder): View
    {
        $activeSession = $sessions->activeSession();
        $testSession = $sessions->testSession();
        $cameras = Camera::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $defaultSource = Setting::kioskDefaultSource();
        if (str_starts_with($defaultSource, 'cctv:')) {
            $camId = (int) substr($defaultSource, 5);
            if (! $cameras->contains('id', $camId)) {
                $defaultSource = 'webcam';
            }
        }

        return view('kiosk.index', [
            'activeSession' => $activeSession,
            'testSession' => $testSession,
            'cameras' => $cameras,
            'defaultSource' => $defaultSource,
            'serviceReady' => $encoder->isAvailable(),
            'recentLogs' => $this->recentLogsPayload(),
            'sessionPayload' => $this->activeSessionPayload($sessions),
        ]);
    }

    /**
     * Ambil data terbaru untuk pembaruan langsung (polling) di Layar Monitor.
     */
    public function data(Request $request, SolatSessionService $sessions): JsonResponse
    {
        if (session()->isStarted()) {
            session()->save();
        }

        $cameraId = (int) $request->query('camera_id', 0);
        $lastScan = $cameraId > 0 ? Cache::get('cctv_last_scan_'.$cameraId) : null;

        $workerStatus = Cache::get(CctvAutoScanCommand::CacheKey);
        $isWorkerActive = ! empty($workerStatus['running'])
            && (now()->timestamp - ($workerStatus['last_beat'] ?? 0) <= 25);

        return response()->json([
            'server_time' => now()->format('H:i:s'),
            'server_date' => now()->translatedFormat('l, d F Y'),
            'session' => $this->activeSessionPayload($sessions),
            'logs' => $this->recentLogsPayload(),
            'worker_active' => $isWorkerActive,
            'last_scan' => $lastScan,
        ]);
    }

    /**
     * Pindai satu bingkai gambar dari Webcam PC di halaman Layar Monitor.
     */
    public function scanWebcam(
        Request $request,
        SolatAttendanceService $attendance,
        SolatSessionService $sessions,
    ): JsonResponse {
        $request->validate([
            'photo' => ['required', 'image', 'max:5120'],
        ], [
            'photo.required' => 'Potret tidak diterima kamera.',
            'photo.image' => 'Berkas potret bukan gambar.',
            'photo.max' => 'Ukuran potret maksimal 5 MB.',
        ]);

        if (session()->isStarted()) {
            session()->save();
        }

        $path = $request->file('photo')->getRealPath();
        $contents = $path !== false ? file_get_contents($path) : false;

        if ($contents === false) {
            return response()->json(['error' => 'Foto tidak terbaca'], 422);
        }

        $result = $attendance->recordFacesFromImage(
            $contents,
            config('face.device_id', 'kiosk-webcam'),
            now(),
            allowTestSession: true,
        );

        return response()->json($this->sanitizeScanResult($result, $sessions));
    }

    /**
     * Pindai satu bingkai gambar dari kamera CCTV yang sedang ditampilkan di Layar Monitor.
     */
    public function scanCamera(
        Request $request,
        Camera $camera,
        SolatAttendanceService $attendance,
        SolatSessionService $sessions,
    ): JsonResponse {
        $request->validate([
            'photo' => ['required', 'image', 'max:5120'],
        ], [
            'photo.required' => 'Cuplikan potret tidak diterima.',
            'photo.image' => 'Berkas potret bukan gambar valid.',
            'photo.max' => 'Ukuran potret maksimal 5 MB.',
        ]);

        if (session()->isStarted()) {
            session()->save();
        }

        $path = $request->file('photo')->getRealPath();
        $contents = $path !== false ? file_get_contents($path) : false;

        if ($contents === false) {
            return response()->json(['error' => 'Foto tidak terbaca'], 422);
        }

        $deviceId = 'cctv-'.$camera->id;
        $result = $attendance->recordFacesFromImage($contents, $deviceId, now(), allowTestSession: true);

        $sanitized = $this->sanitizeScanResult($result, $sessions);

        Cache::put('cctv_last_scan_'.$camera->id, [
            'faces' => $sanitized['faces'] ?? [],
            'width' => $sanitized['width'] ?? 1920,
            'height' => $sanitized['height'] ?? 1080,
            'timestamp' => now()->timestamp,
        ], 12);

        return response()->json($sanitized);
    }

    /**
     * Susun daftar 15 log kehadiran terbaru (hanya nama, waktu sholat, jam, dan status).
     *
     * @return array<int, array{id: int, name: string, prayer: string, time: string, status: string}>
     */
    private function recentLogsPayload(): array
    {
        return SolatAttendanceLog::query()
            ->with(['user:id,name', 'session:id,prayer_name,is_test'])
            ->latest('scanned_at')
            ->limit(15)
            ->get()
            ->map(static fn (SolatAttendanceLog $log): array => [
                'id' => (int) $log->id,
                'name' => (string) ($log->user?->name ?? 'Jamaah'),
                'prayer' => (string) ($log->session?->prayerLabel() ?? 'Sholat'),
                'time' => (string) ($log->scanned_at?->format('H:i:s') ?? '--:--:--'),
                'status' => (string) $log->statusLabel(),
            ])
            ->values()
            ->all();
    }

    /**
     * Ringkasan keadaan sesi sholat saat ini untuk header Layar Monitor.
     *
     * @return array{active: bool, is_test: bool, label: string, window: string, total: int, present: int, late: int}
     */
    private function activeSessionPayload(SolatSessionService $sessions): array
    {
        $active = $sessions->activeSession();
        $test = $sessions->testSession();

        $current = $active ?? ($test?->isOpen() ? $test : null);

        if ($current === null) {
            return [
                'active' => false,
                'is_test' => false,
                'label' => 'Belum Ada Sesi Dibuka',
                'window' => 'Menunggu jadwal waktu sholat berikutnya',
                'total' => 0,
                'present' => 0,
                'late' => 0,
            ];
        }

        $present = $current->logs()->where('status', SolatAttendanceLog::StatusHadir)->count();
        $late = $current->logs()->where('status', SolatAttendanceLog::StatusTerlambat)->count();

        return [
            'active' => true,
            'is_test' => (bool) $current->is_test,
            'label' => $current->is_test ? 'Mode Uji Coba' : 'Sholat '.$current->prayerLabel(),
            'prayer_short' => $current->prayerLabel(),
            'window' => $current->is_test ? 'Percobaan kamera aktif' : $current->windowLabel(),
            'total' => $present + $late,
            'present' => $present,
            'late' => $late,
        ];
    }

    /**
     * Bersihkan URL foto bukti dari balasan scan agar Layar Monitor hanya memuat
     * koordinat kotak wajah, nama, waktu sholat, dan status.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function sanitizeScanResult(array $result, SolatSessionService $sessions): array
    {
        unset($result['proof_url'], $result['proof_photo_url']);

        $sessionInfo = $this->activeSessionPayload($sessions);
        $prayerName = $sessionInfo['active'] ? ($sessionInfo['prayer_short'] ?? $sessionInfo['label']) : 'Sholat';

        if (isset($result['faces']) && is_array($result['faces'])) {
            foreach ($result['faces'] as &$face) {
                if (isset($face['attendance']) && is_array($face['attendance'])) {
                    unset($face['attendance']['proof_photo_url'], $face['attendance']['proof_url']);
                    $face['attendance']['prayer'] = $prayerName;
                }
            }
            unset($face);
        }

        $result['session_summary'] = $sessionInfo;

        return $result;
    }
}
