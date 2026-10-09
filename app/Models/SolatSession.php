<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'prayer_name',
    'date',
    'start_time',
    'end_time',
    'status',
    'opened_at',
    'closed_at',
    'opened_by',
    'closed_by',
    'note',
])]
class SolatSession extends Model
{
    public const StatusBelumDibuka = 'belum_dibuka';

    public const StatusSedangDibuka = 'sedang_dibuka';

    public const StatusSudahDitutup = 'sudah_ditutup';

    public const StatusTerjadwal = 'terjadwal';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<SolatAttendanceLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(SolatAttendanceLog::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isOpen(): bool
    {
        return $this->status === self::StatusSedangDibuka;
    }

    public function isClosed(): bool
    {
        return $this->status === self::StatusSudahDitutup;
    }

    public function prayerLabel(): string
    {
        $defaults = collect(config('solat.prayer_defaults'))->keyBy('prayer_name');

        return (string) ($defaults->get($this->prayer_name)['label'] ?? ucfirst((string) $this->prayer_name));
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::StatusSedangDibuka => 'Sedang dibuka',
            self::StatusSudahDitutup => 'Sudah ditutup',
            self::StatusTerjadwal => 'Terjadwal',
            default => 'Belum dibuka',
        };
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            self::StatusSedangDibuka => 'success',
            self::StatusSudahDitutup => 'info',
            self::StatusTerjadwal => 'warning',
            default => 'muted',
        };
    }

    public function shortTime(?string $value): string
    {
        return $value ? substr($value, 0, 5) : '-';
    }

    public function windowLabel(): string
    {
        return $this->shortTime($this->start_time).' sampai '.$this->shortTime($this->end_time);
    }

    public function label(): string
    {
        return $this->prayerLabel().' '.$this->date?->translatedFormat('d M Y');
    }

    /**
     * @param  Builder<SolatSession>  $query
     */
    public function scopeForDate(Builder $query, mixed $date): void
    {
        $query->whereDate('date', $date);
    }

    /**
     * @param  Builder<SolatSession>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', self::StatusSedangDibuka);
    }
}
