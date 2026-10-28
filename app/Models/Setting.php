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
    public const KeyFaceCctvTolerance = 'face_cctv_tolerance';
    public const KeyFaceTolerance = 'face_tolerance';
    public const KeyFaceCctvMinFaceSize = 'face_cctv_min_face_size';
    public const KeyFaceMinFaceSize = 'face_min_face_size';
    public const KeyKioskDefaultSource = 'kiosk_default_source';

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

    /**
     * Ambang toleransi jarak pencocokan wajah khusus kamera CCTV.
     */
    public static function faceCctvTolerance(): float
    {
        $val = static::get(self::KeyFaceCctvTolerance, config('face.cctv_tolerance', 1.15));

        return (float) $val;
    }

    /**
     * Ambang toleransi jarak pencocokan wajah untuk kamera petugas/webcam.
     */
    public static function faceTolerance(): float
    {
        $val = static::get(self::KeyFaceTolerance, config('face.tolerance', 1.15));

        return (float) $val;
    }

    /**
     * Batas minimal ukuran sisi wajah kamera CCTV dalam piksel.
     */
    public static function faceCctvMinFaceSize(): int
    {
        $val = static::get(self::KeyFaceCctvMinFaceSize, config('face.cctv_min_face_size', 40));

        return (int) $val;
    }

    /**
     * Batas minimal ukuran sisi wajah kamera petugas dalam piksel.
     */
    public static function faceMinFaceSize(): int
    {
        $val = static::get(self::KeyFaceMinFaceSize, config('face.min_face_size', 80));

        return (int) $val;
    }

    /**
     * Sumber kamera bawaan untuk halaman Layar Monitor (/layar): 'webcam' atau 'cctv:{id}'.
     */
    public static function kioskDefaultSource(): string
    {
        $val = trim((string) static::get(self::KeyKioskDefaultSource, 'webcam'));

        return $val !== '' ? $val : 'webcam';
    }
}
