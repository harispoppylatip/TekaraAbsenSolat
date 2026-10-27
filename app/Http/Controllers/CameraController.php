<?php

namespace App\Http\Controllers;

use App\Console\Commands\CctvAutoScanCommand;
use App\Models\Camera;
use App\Models\SolatAttendanceLog;
use App\Services\CameraStreamService;
use App\Services\SolatAttendanceService;
use App\Services\SolatSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CameraController extends Controller
{
    /**
     * Tampilkan pemantau siaran CCTV dan daftar kamera.
     */
    public function index(Request $request, SolatSessionService $sessions, CameraStreamService $streamService): View
    {
        $cameras = Camera::query()
            ->latest()
            ->get();

        $selectedCameraId = $request->query('kamera');
        $selectedCamera = null;

        if ($selectedCameraId) {
            $selectedCamera = $cameras->firstWhere('id', (int) $selectedCameraId);
        }

        if ($selectedCamera === null) {
            $selectedCamera = $cameras->firstWhere('is_active', true) ?: $cameras->first();
        }

        if ($selectedCamera !== null && $selectedCamera->isRtsp()) {
            $streamService->ensureGo2RtcStream($selectedCamera);
        }

        $stats = [
            'total' => $cameras->count(),
            'aktif' => $cameras->where('is_active', true)->count(),
            'web_stream' => $cameras->where('stream_type', Camera::TypeWebStream)->count(),
            'rtsp' => $cameras->where('stream_type', Camera::TypeRtsp)->count(),
        ];

        $types = [
            Camera::TypeWebStream => 'Web Stream (HTTP / fMP4 / HLS)',
            Camera::TypeRtsp => 'RTSP CCTV (Kamera Biasa)',
        ];

        $recent = SolatAttendanceLog::query()
            ->with(['user', 'session'])
            ->latest('scanned_at')
            ->limit(10)
            ->get();

        $workerStatus = Cache::get(CctvAutoScanCommand::CacheKey);
        $isWorkerActive = ! empty($workerStatus['running']) && (now()->timestamp - ($workerStatus['last_beat'] ?? 0) <= 25);

        return view('cameras.index', [
            'cameras' => $cameras,
            'selectedCamera' => $selectedCamera,
            'stats' => $stats,
            'types' => $types,
            'activeSession' => $sessions->activeSession(),
            'testSession' => $sessions->testSession(),
            'recent' => $recent,
            'isWorkerActive' => $isWorkerActive,
        ]);
    }

    /**
     * Simpan kamera siaran baru ke sistem.
     */
    public function store(Request $request, CameraStreamService $streamService): RedirectResponse
    {
        $validated = $this->validateCameraInput($request, $streamService);

        $validated['is_active'] = $request->boolean('is_active', true);

        $camera = Camera::create($validated);

        if ($camera->isRtsp()) {
            $streamService->ensureGo2RtcStream($camera);
        }

        return redirect()
            ->route('cameras.index', ['kamera' => $camera->id])
            ->with('status', 'Kamera ' . $validated['name'] . ' berhasil ditambahkan.');
    }

    /**
     * Tampilkan form ubah kamera.
     */
    public function edit(Camera $camera): View
    {
        $types = [
            Camera::TypeWebStream => 'Web Stream (HTTP / fMP4 / HLS)',
            Camera::TypeRtsp => 'RTSP CCTV (Kamera Biasa)',
        ];

        return view('cameras.edit', [
            'camera' => $camera,
            'types' => $types,
        ]);
    }

    /**
     * Perbarui data kamera siaran.
     */
    public function update(Request $request, Camera $camera, CameraStreamService $streamService): RedirectResponse
    {
        $validated = $this->validateCameraInput($request, $streamService);

        $validated['is_active'] = $request->boolean('is_active', false);

        $camera->update($validated);

        if ($camera->isRtsp()) {
            $streamService->ensureGo2RtcStream($camera);
        }

        return redirect()
            ->route('cameras.index', ['kamera' => $camera->id])
            ->with('status', 'Data kamera ' . $camera->name . ' berhasil diperbarui.');
    }

    /**
     * Ubah status aktif / nonaktif kamera.
     */
    public function toggleStatus(Camera $camera): RedirectResponse
    {
        $camera->update([
            'is_active' => ! $camera->is_active,
        ]);

        $keadaan = $camera->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('status', 'Kamera ' . $camera->name . ' telah ' . $keadaan . '.');
    }

    /**
     * Hapus kamera siaran dari daftar.
     */
    public function destroy(Camera $camera): RedirectResponse
    {
        $name = $camera->name;
        $camera->delete();

        return redirect()
            ->route('cameras.index')
            ->with('status', 'Kamera ' . $name . ' berhasil dihapus.');
    }

    /**
     * Uji koneksi URL siaran kamera (AJAX).
     */
    public function testConnection(Request $request, CameraStreamService $service): JsonResponse
    {
        $validated = $request->validate([
            'stream_type' => ['required', 'in:' . Camera::TypeWebStream . ',' . Camera::TypeRtsp],
            'stream_url' => [
                'required',
                'string',
                'max:1000',
                function (string $attribute, mixed $value, \Closure $fail) use ($request, $service): void {
                    $type = (string) $request->input('stream_type', Camera::TypeWebStream);
                    $error = $service->validateStreamUrl((string) $value, $type);
                    if ($error !== null) {
                        $fail($error);
                    }
                },
            ],
            'snapshot_url' => [
                'nullable',
                'string',
                'max:1000',
                function (string $attribute, mixed $value, \Closure $fail) use ($service): void {
                    if ($value === null || trim((string) $value) === '') {
                        return;
                    }
                    $error = $service->validateSnapshotUrl((string) $value);
                    if ($error !== null) {
                        $fail($error);
                    }
                },
            ],
        ]);

        $result = $service->testConnection(
            $validated['stream_type'],
            $validated['stream_url'],
            $validated['snapshot_url'] ?? null
        );

        return response()->json($result);
    }

    /**
     * Ambil satu cuplikan potret gambar (JPEG) dari kamera.
     */
    public function snapshot(Camera $camera, CameraStreamService $service): Response
    {
        if (session()->isStarted()) {
            session()->save();
        }

        $image = $service->captureSnapshot($camera);

        if ($image === null) {
            abort(502, 'Gagal mengambil cuplikan dari kamera.');
        }

        return response($image, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Alirkan siaran langsung kamera melalui proxy server.
     */
    public function stream(Camera $camera, CameraStreamService $service): StreamedResponse
    {
        if (session()->isStarted()) {
            session()->save();
        }

        return $service->streamResponse($camera);
    }

    /**
     * Terima satu cuplikan potret dari pemantau CCTV, lalu periksa seluruh wajah
     * yang terlihat untuk dicatat kehadirannya secara otomatis.
     */
    public function scan(Request $request, Camera $camera, SolatAttendanceService $attendance): JsonResponse
    {
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

        $deviceId = 'cctv-' . $camera->id;
        $result = $attendance->recordFacesFromImage($contents, $deviceId, now(), allowTestSession: true);

        Cache::put('cctv_last_scan_' . $camera->id, [
            'faces' => $result['faces'] ?? [],
            'width' => $result['width'] ?? 1920,
            'height' => $result['height'] ?? 1080,
            'timestamp' => now()->timestamp,
        ], 12);

        return response()->json($result);
    }

    /**
     * Ambil daftar riwayat log absen terakhir untuk pembaruan realtime di layar CCTV.
     */
    public function logs(Camera $camera, \App\Services\SolatProofService $proofs): JsonResponse
    {
        if (session()->isStarted()) {
            session()->save();
        }

        $recent = SolatAttendanceLog::query()
            ->with(['user', 'session'])
            ->latest('scanned_at')
            ->limit(10)
            ->get()
            ->map(function (SolatAttendanceLog $log) use ($proofs) {
                return [
                    'name' => $log->user?->name ?? 'Anggota',
                    'identity' => $log->user?->class_name ?: $log->user?->roleLabel(),
                    'time' => $log->scanned_at?->format('H:i:s'),
                    'session' => $log->session?->prayerLabel() ?? 'Uji Coba',
                    'status' => $log->statusLabel(),
                    'is_fingerprint' => $log->isFingerprint(),
                    'device_id' => $log->device_id,
                    'proof_photo_url' => $log->isFingerprint() ? null : $proofs->url($log),
                ];
            });

        $workerStatus = Cache::get(CctvAutoScanCommand::CacheKey);
        $isWorkerActive = ! empty($workerStatus['running']) && (now()->timestamp - ($workerStatus['last_beat'] ?? 0) <= 25);
        $lastScan = Cache::get('cctv_last_scan_' . $camera->id);

        return response()->json([
            'logs' => $recent,
            'worker_active' => $isWorkerActive,
            'last_scan' => $lastScan,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCameraInput(Request $request, CameraStreamService $service): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'stream_type' => ['required', 'in:' . Camera::TypeWebStream . ',' . Camera::TypeRtsp],
            'stream_url' => [
                'required',
                'string',
                'max:1000',
                function (string $attribute, mixed $value, \Closure $fail) use ($request, $service): void {
                    $type = (string) $request->input('stream_type', Camera::TypeWebStream);
                    $error = $service->validateStreamUrl((string) $value, $type);
                    if ($error !== null) {
                        $fail($error);
                    }
                },
            ],
            'snapshot_url' => [
                'nullable',
                'string',
                'max:1000',
                function (string $attribute, mixed $value, \Closure $fail) use ($service): void {
                    if ($value === null || trim((string) $value) === '') {
                        return;
                    }
                    $error = $service->validateSnapshotUrl((string) $value);
                    if ($error !== null) {
                        $fail($error);
                    }
                },
            ],
            'location' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'name.required' => 'Nama kamera wajib diisi.',
            'stream_type.required' => 'Pilih jenis siaran kamera.',
            'stream_url.required' => 'Alamat URL siaran kamera wajib diisi.',
        ]);
    }
}
