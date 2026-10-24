<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'solat_session_id', 'scanned_at', 'status', 'match_distance', 'device_id', 'proof_photo_path'])]
class SolatAttendanceLog extends Model
{
    public const StatusHadir = 'hadir';

    public const StatusTerlambat = 'terlambat';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
            'match_distance' => 'float',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<SolatSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(SolatSession::class, 'solat_session_id');
    }

    public function statusLabel(): string
    {
        return $this->status === self::StatusTerlambat ? 'Terlambat' : 'Hadir';
    }

    public function statusTone(): string
    {
        return $this->status === self::StatusTerlambat ? 'warning' : 'success';
    }

    /**
     * Apakah catatan kehadiran ini berasal dari pemindaian sensor sidik jari.
     */
    public function isFingerprint(): bool
    {
        if ($this->device_id === null || $this->device_id === '') {
            return false;
        }

        if ($this->proof_photo_path !== null && $this->proof_photo_path !== '') {
            return false;
        }

        if (str_starts_with($this->device_id, 'cctv-') || str_starts_with($this->device_id, 'kamera-')) {
            return false;
        }

        $cameraDevices = array_filter([
            config('solat.device_id', 'kamera-wajah'),
            config('face.device_id', 'kamera-wajah'),
            'kamera-wajah',
        ]);

        return ! in_array($this->device_id, $cameraDevices, true);
    }

    /**
     * Keterangan singkat cara absen dilakukan.
     */
    public function methodLabel(): string
    {
        if ($this->isFingerprint()) {
            return 'absen via sidik jari';
        }

        if ($this->proof_photo_path !== null || ($this->device_id !== null && $this->device_id !== '')) {
            return 'absen via kamera wajah';
        }

        return 'dicatat petugas';
    }

    /**
     * Kalimat ringkas log kehadiran (misal: "Hari Poppy Latip absen via sidik jari pukul 12:05").
     */
    public function summaryLine(): string
    {
        $name = $this->user?->name ?? 'Anggota';
        $time = $this->scanned_at?->format('H:i:s') ?? '-';

        return "{$name} {$this->methodLabel()} pukul {$time}";
    }
}
