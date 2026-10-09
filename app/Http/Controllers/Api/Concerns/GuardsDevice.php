<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Device;
use App\Services\DeviceRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait GuardsDevice
{
    /**
     * Periksa kode perangkat yang dikirim alat.
     *
     * Perangkat yang belum didaftarkan atau sedang dimatikan tidak boleh
     * memakai layanan apa pun. Balasannya selalu berbentuk keterangan yang
     * bisa langsung dicetak di layar alat.
     *
     * @return array{0: Device|null, 1: JsonResponse|null}
     */
    protected function guardDevice(Request $request, DeviceRegistryService $devices): array
    {
        $raw = $request->input('device_id', $request->header('X-Device-Id'));
        $device = $devices->find(is_string($raw) ? $raw : null);

        $refusal = $devices->refusal($device);

        if ($refusal !== null) {
            return [null, response()->json([
                'ok' => false,
                'blocked' => true,
                'status' => $refusal['code'],
                'label' => $refusal['display_message'],
                'message' => $refusal['message'],
                'display_message' => $refusal['display_message'],
                'display_detail' => $refusal['display_detail'],
                'member' => null,
                'log' => null,
            ], $refusal['http'])];
        }

        if ($device instanceof Device) {
            $devices->touch($device);
        }

        return [$device, null];
    }
}
