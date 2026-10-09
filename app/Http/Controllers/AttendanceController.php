<?php

namespace App\Http\Controllers;

use App\Models\SolatAttendanceLog;
use App\Models\SolatSession;
use App\Services\FaceEncodingService;
use App\Services\SolatAttendanceService;
use App\Services\SolatSessionService;
use App\Support\SensorReply;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(SolatSessionService $sessions, FaceEncodingService $encoder): View
    {
        return view('attendance.wajah', [
            'activeSession' => $sessions->activeSession(),
            'serviceReady' => $encoder->isAvailable(),
            'serviceMessage' => $encoder->unavailableMessage(),
            'todaySessions' => SolatSession::query()
                ->forDate(Carbon::today())
                ->withCount('logs')
                ->orderBy('start_time')
                ->get(),
            'recent' => SolatAttendanceLog::query()
                ->with(['user', 'session'])
                ->latest('scanned_at')
                ->limit(10)
                ->get(),
        ]);
    }

    /**
     * Terima satu potret dari kamera petugas.
     */
    public function scan(Request $request, SolatAttendanceService $attendance): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'max:5120'],
        ], [
            'photo.required' => 'Potret tidak diterima kamera.',
            'photo.image' => 'Berkas potret bukan gambar.',
            'photo.max' => 'Ukuran potret paling besar 5 MB.',
        ]);

        $path = $request->file('photo')->getRealPath();
        $contents = $path !== false ? file_get_contents($path) : false;

        if ($contents === false) {
            $reply = SensorReply::refusal('invalid_request', 'Foto tak terbaca', 422);

            return response()->json($reply['payload'], $reply['http']);
        }

        $result = $attendance->recordFromImage($contents, config('face.device_id'), now());

        return response()->json(SensorReply::fromResult($result));
    }
}
