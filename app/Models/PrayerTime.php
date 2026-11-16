<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['prayer_name', 'label', 'start_time', 'end_time', 'late_minutes', 'sort_order', 'is_active', 'active_days'])]
class PrayerTime extends Model
{
    /**
     * Daftar 6 waktu solat standar sekolah (tetap, bisa diedit jam/toleransinya
     * dan dipilih aktif/tidaknya menggunakan cekbox, namun tidak dapat dihapus).
     *
     * @var list<array{prayer_name: string, label: string, start_time: string, end_time: string, sort_order: int, is_active: bool}>
     */
    public const StandardPrayers = [
        ['prayer_name' => 'subuh', 'label' => 'Subuh', 'start_time' => '04:45', 'end_time' => '05:30', 'sort_order' => 1, 'is_active' => true],
        ['prayer_name' => 'duha', 'label' => 'Duha', 'start_time' => '07:30', 'end_time' => '08:30', 'sort_order' => 2, 'is_active' => true],
        ['prayer_name' => 'zuhur', 'label' => 'Zuhur', 'start_time' => '12:00', 'end_time' => '12:45', 'sort_order' => 3, 'is_active' => true],
        ['prayer_name' => 'ashar', 'label' => 'Ashar', 'start_time' => '15:15', 'end_time' => '16:00', 'sort_order' => 4, 'is_active' => true],
        ['prayer_name' => 'maghrib', 'label' => 'Maghrib', 'start_time' => '18:05', 'end_time' => '18:50', 'sort_order' => 5, 'is_active' => true],
        ['prayer_name' => 'isya', 'label' => 'Isya', 'start_time' => '19:05', 'end_time' => '19:50', 'sort_order' => 6, 'is_active' => true],
    ];

    /**
     * Pastikan ke-6 waktu solat standar tersedia di basis data dengan urutan yang rapi.
     */
    public static function ensureStandardPrayers(array $defaultActiveDays = [1, 2, 3, 4, 5]): void
    {
        $existingDays = static::query()
            ->whereNotNull('active_days')
            ->orderBy('sort_order')
            ->value('active_days');

        $days = is_array($existingDays) && $existingDays !== []
            ? $existingDays
            : (array) config('solat.active_days', $defaultActiveDays);

        $defaultLate = (int) config('solat.late_minutes', 10);

        foreach (self::StandardPrayers as $item) {
            $record = static::query()->where('prayer_name', $item['prayer_name'])->first();

            if ($record === null) {
                static::query()->create([
                    'prayer_name' => $item['prayer_name'],
                    'label' => $item['label'],
                    'start_time' => $item['start_time'],
                    'end_time' => $item['end_time'],
                    'late_minutes' => $defaultLate,
                    'sort_order' => $item['sort_order'],
                    'is_active' => $item['is_active'],
                    'active_days' => $days,
                ]);
            } elseif ((int) $record->sort_order !== $item['sort_order']) {
                $record->update(['sort_order' => $item['sort_order']]);
            }
        }
    }
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
