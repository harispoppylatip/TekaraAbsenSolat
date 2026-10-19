<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
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
