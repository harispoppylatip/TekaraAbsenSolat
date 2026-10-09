<?php

namespace App\Support;

use App\Models\Device;
use App\Models\SolatAttendanceLog;
use App\Models\SolatSession;

/**
 * Penerjemah keadaan menjadi kelas warna yang sudah ada di lembar gaya.
 *
 * Keadaan disimpan di basis data sebagai kode, sedangkan tampilan memakai nama
 * kelas. Penerjemahan dikumpulkan di satu tempat supaya penanda warna di semua
 * halaman selalu sama artinya.
 */
class Status
{
    /**
     * Nada dari SolatSession::statusTone() diubah menjadi kelas lembar gaya.
     */
    public static function tone(?string $tone): string
    {
        return match ($tone) {
            'success' => 'status-open',
            'info' => 'status-closed',
            'warning' => 'status-wait',
            'error' => 'status-error',
            default => 'status-off',
        };
    }

    public static function session(?SolatSession $session): string
    {
        return self::tone($session?->statusTone());
    }

    public static function log(?string $status): string
    {
        return $status === SolatAttendanceLog::StatusTerlambat ? 'status-late' : 'status-open';
    }

    public static function device(?Device $device): string
    {
        return match ($device?->status) {
            Device::StatusAktif => 'status-open',
            Device::StatusNonaktif => 'status-error',
            default => 'status-wait',
        };
    }
}
