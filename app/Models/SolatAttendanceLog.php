<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'solat_session_id', 'scanned_at', 'status', 'match_distance', 'device_id'])]
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
}
