<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['prayer_name', 'label', 'start_time', 'end_time', 'late_minutes', 'sort_order', 'is_active', 'active_days'])]
class PrayerTime extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'late_minutes' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'active_days' => 'array',
        ];
    }

    /**
     * @param  Builder<PrayerTime>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('start_time');
    }

    /**
     * @param  Builder<PrayerTime>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function windowLabel(): string
    {
        return $this->shortTime($this->start_time).' sampai '.$this->shortTime($this->end_time);
    }

    public function shortTime(?string $value): string
    {
        return $value ? substr($value, 0, 5) : '-';
    }
}
