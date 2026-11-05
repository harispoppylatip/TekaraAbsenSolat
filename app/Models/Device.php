<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['device_id', 'name', 'type', 'status', 'location', 'last_seen_at'])]
class Device extends Model
{
    public const StatusBelumDidaftarkan = 'belum_didaftarkan';

    public const StatusAktif = 'aktif';

    public const StatusNonaktif = 'nonaktif';

    public const TypeSidikJari = 'sidik_jari';

    public const TypeGerbang = 'gerbang';

    public const TypeKelas = 'kelas';

    /**
     * Daftar ID perangkat kamera bawaan yang tertanam permanen di sistem
     * sehingga tidak perlu ditampilkan di menu Perangkat.
     *
     * @return list<string>
     */
    public static function builtInIds(): array
    {
        return array_values(array_unique(array_filter([
            'kamera-wajah',
            'layar-monitor',
            (string) config('solat.device_id', 'kamera-wajah'),
            (string) config('face.device_id', 'kamera-wajah'),
        ])));
    }

    /**
     * Apakah ID perangkat termasuk kamera bawaan permanen.
     */
    public static function isBuiltIn(?string $deviceId): bool
    {
        $id = trim((string) $deviceId);
        if ($id === '') {
            return false;
        }

        return in_array($id, self::builtInIds(), true)
            || str_starts_with($id, 'kamera-')
            || str_starts_with($id, 'cctv-');
    }

    /**
     * Saring hanya perangkat keras eksternal (bukan kamera bawaan yang tertanam).
     *
     * @param  Builder<Device>  $query
     */
    public function scopeHardware(Builder $query): void
    {
        $query->whereNotIn('device_id', self::builtInIds())
            ->where('device_id', 'not like', 'kamera-%')
            ->where('device_id', 'not like', 'cctv-%');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    public function isRegistered(): bool
    {
        return $this->status !== self::StatusBelumDidaftarkan;
    }

    public function isActive(): bool
    {
        return $this->status === self::StatusAktif;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::StatusAktif => 'Aktif',
            self::StatusNonaktif => 'Dimatikan',
            default => 'Belum didaftarkan',
        };
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TypeSidikJari => 'Sensor sidik jari',
            self::TypeKelas => 'Ruang kelas',
            default => 'Gerbang',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            self::StatusAktif => 'Aktif',
            self::StatusNonaktif => 'Dimatikan',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function types(): array
    {
        return [
            self::TypeSidikJari => 'Sensor sidik jari',
            self::TypeGerbang => 'Gerbang',
            self::TypeKelas => 'Ruang kelas',
        ];
    }
}
