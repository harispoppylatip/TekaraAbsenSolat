<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Carbon;

/**
 * Pendaftaran dan izin pakai perangkat sensor.
 *
 * Perangkat baru yang mengirim data otomatis tercatat di sini dengan status
 * belum didaftarkan, supaya operator bisa melihatnya di halaman Perangkat.
 * Perangkat itu belum boleh mengirim absen sampai didaftarkan dan dinyalakan.
 */
class DeviceRegistryService
{
    /**
     * Batas maksimum antrean perangkat berstatus belum_didaftarkan
     * untuk mencegah banjir baris basis data dari permintaan tanpa autentikasi.
     */
    public const MaxPendingDevices = 30;

    /**
     * Cari perangkat, dan catat perangkat baru bila belum dikenal.
     */
    public function find(?string $deviceId, ?string $fallbackName = null): ?Device
    {
        $id = trim((string) $deviceId);

        if ($id === '' || ! preg_match('/^[A-Za-z0-9._:-]{1,50}$/', $id)) {
            return null;
        }

        $existing = Device::query()->where('device_id', $id)->first();
        if ($existing !== null) {
            return $existing;
        }

        $pendingCount = Device::query()
            ->where('status', Device::StatusBelumDidaftarkan)
            ->count();

        if ($pendingCount >= self::MaxPendingDevices) {
            return new Device([
                'device_id' => $id,
                'name' => $fallbackName ?: $id,
                'type' => Device::TypeGerbang,
                'status' => Device::StatusBelumDidaftarkan,
            ]);
        }

        return Device::query()->firstOrCreate(
            ['device_id' => $id],
            [
                'name' => $fallbackName ?: $id,
                'type' => Device::TypeGerbang,
                'status' => Device::StatusBelumDidaftarkan,
            ],
        );
    }

    /**
     * Tandai perangkat baru saja terdengar.
     */
    public function touch(Device $device): void
    {
        $device->forceFill(['last_seen_at' => Carbon::now()])->save();
    }

    /**
     * Alasan sebuah perangkat tidak boleh dipakai. Null berarti boleh dipakai.
     *
     * Dipisah dari pembatalan permintaan supaya pemanggilnya bisa memilih
     * bentuk balasan: halaman web memakai pesan biasa, perangkat sensor memakai
     * balasan JSON berisi teks layar.
     *
     * @return array{code: string, message: string, display_message: string, display_detail: string, http: int}|null
     */
    public function refusal(?Device $device): ?array
    {
        if ($device === null) {
            return [
                'code' => 'device_id_missing',
                'message' => 'Kode perangkat kosong. Isi kode perangkat pada alat.',
                'display_message' => 'Kode alat kosong',
                'display_detail' => 'Lapor operator',
                'http' => 422,
            ];
        }

        if (! $device->isRegistered()) {
            return [
                'code' => 'device_unregistered',
                'message' => 'Perangkat '.$device->device_id.' belum didaftarkan. Daftarkan dulu di halaman Perangkat.',
                'display_message' => 'Belum terdaftar',
                'display_detail' => 'Lapor operator',
                'http' => 403,
            ];
        }

        if (! $device->isActive()) {
            return [
                'code' => 'device_inactive',
                'message' => 'Perangkat '.$device->device_id.' sedang dimatikan. Nyalakan dulu di halaman Perangkat.',
                'display_message' => 'Sensor nonaktif',
                'display_detail' => 'Lapor operator',
                'http' => 403,
            ];
        }

        return null;
    }
}
