<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\GuardsDevice;
use App\Http\Controllers\Controller;
use App\Services\DeviceRegistryService;
use App\Services\SolatAttendanceService;
use App\Services\SolatSessionService;
use App\Support\SensorReply;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SolatScanController extends Controller
{
    use GuardsDevice;

    /**
     * Terima satu potret dari kamera gerbang.
     */
    public function scan(
        Request $request,
        DeviceRegistryService $devices,
        SolatAttendanceService $attendance,
    ): JsonResponse {
        [$device, $refusal] = $this->guardDevice($request, $devices);

        if ($refusal !== null) {
            return $refusal;
        }

        $contents = $this->readPhoto($request);

        if ($contents === null) {
            $reply = SensorReply::refusal('invalid_request', 'Data alat kurang', 422);

            return response()->json($reply['payload'], $reply['http']);
        }

        $result = $attendance->recordFromImage($contents, $device?->device_id, now());

        return response()->json(SensorReply::fromResult($result));
    }

    /**
     * Keadaan sesi absen sekarang, dipakai alat untuk menulis layarnya.
     */
    public function session(
        Request $request,
        DeviceRegistryService $devices,
        SolatSessionService $sessions,
    ): JsonResponse {
        [, $refusal] = $this->guardDevice($request, $devices);

        if ($refusal !== null) {
            return $refusal;
        }

        $session = $sessions->activeSession();

        return response()->json([
            'ok' => $session !== null,
            'blocked' => false,
            'status' => $session === null ? 'no_session' : 'open',
            'label' => $session === null ? 'Belum ada sesi' : $session->prayerLabel(),
            'message' => $session === null
                ? 'Belum ada sesi absen yang dibuka. Minta petugas membuka sesi lebih dulu.'
                : 'Sesi '.$session->prayerLabel().' sedang dibuka, tempelkan wajah untuk absen.',
            'display_message' => $session === null ? 'Sesi belum buka' : $session->prayerLabel(),
            'display_detail' => $session === null ? SensorReply::DetailFallback : 'Absen siap',
            'session' => $session === null ? null : [
                'prayer_name' => $session->prayer_name,
                'label' => $session->prayerLabel(),
                'date' => $session->date?->toDateString(),
                'start_time' => $session->shortTime($session->start_time),
                'end_time' => $session->shortTime($session->end_time),
                'total' => $session->logs()->count(),
            ],
        ]);
    }

    private function readPhoto(Request $request): ?string
    {
        if (! $request->hasFile('photo')) {
            return null;
        }

        $path = $request->file('photo')->getRealPath();

        if ($path === false) {
            return null;
        }

        $contents = file_get_contents($path);

        return $contents === false ? null : $contents;
    }
}
