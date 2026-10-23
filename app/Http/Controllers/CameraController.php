<?php

namespace App\Http\Controllers;

use App\Models\Camera;
use App\Services\CameraStreamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CameraController extends Controller
{
    /**
     * Tampilkan daftar kamera siaran (CCTV RTSP dan Web Stream).
     */
    public function index(): View
    {
        $cameras = Camera::query()
            ->latest()
            ->get();

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

        return view('cameras.index', [
            'cameras' => $cameras,
            'stats' => $stats,
            'types' => $types,
        ]);
    }

    /**
     * Simpan kamera siaran baru ke sistem.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'stream_type' => ['required', 'in:' . Camera::TypeWebStream . ',' . Camera::TypeRtsp],
            'stream_url' => ['required', 'string'],
            'snapshot_url' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'name.required' => 'Nama kamera wajib diisi.',
            'stream_type.required' => 'Pilih jenis siaran kamera.',
            'stream_url.required' => 'Alamat URL siaran kamera wajib diisi.',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        Camera::create($validated);

        return redirect()
            ->route('cameras.index')
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
    public function update(Request $request, Camera $camera): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'stream_type' => ['required', 'in:' . Camera::TypeWebStream . ',' . Camera::TypeRtsp],
            'stream_url' => ['required', 'string'],
            'snapshot_url' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'name.required' => 'Nama kamera wajib diisi.',
            'stream_type.required' => 'Pilih jenis siaran kamera.',
            'stream_url.required' => 'Alamat URL siaran kamera wajib diisi.',
        ]);

        $validated['is_active'] = $request->boolean('is_active', false);

        $camera->update($validated);

        return redirect()
            ->route('cameras.index')
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
            'stream_url' => ['required', 'string'],
            'snapshot_url' => ['nullable', 'string'],
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
        $image = $service->captureSnapshot($camera);

        if ($image === null) {
            abort(502, 'Gagal mengambil cuplikan dari kamera.');
        }

        return response($image, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * Alirkan siaran langsung kamera melalui proxy server.
     */
    public function stream(Camera $camera, CameraStreamService $service): StreamedResponse
    {
        return $service->streamResponse($camera);
    }
}
