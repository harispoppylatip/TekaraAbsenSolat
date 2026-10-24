<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];

    public const KeyProofBoxOverlay = 'proof_box_overlay';

    /**
     * Ambil nilai pengaturan berdasarkan kunci.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = 'app_setting_' . $key;
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            if (! Schema::hasTable('settings')) {
                return $default;
            }

            $record = static::query()->where('key', $key)->first();
            $val = $record !== null ? $record->value : $default;

            Cache::forever($cacheKey, $val);

            return $val;
        } catch (\Throwable) {
            return $default;
        }
    }

    /**
     * Simpan nilai pengaturan ke basis data dan perbarui cache.
     */
    public static function set(string $key, mixed $value): void
    {
        $val = is_bool($value) ? ($value ? '1' : '0') : (string) $value;

        try {
            if (Schema::hasTable('settings')) {
                static::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $val]
                );
            }
        } catch (\Throwable) {
            // Abaikan bila tabel belum siap
        }

        Cache::forever('app_setting_' . $key, $val);
    }

    /**
     * Apakah foto bukti absen wajib menyertakan kotak pendeteksi wajah dan label nama.
     */
    public static function proofOverlayEnabled(): bool
    {
        $val = static::get(self::KeyProofBoxOverlay, '1');

        return in_array($val, ['1', 'true', true, 1], true);
    }
}
