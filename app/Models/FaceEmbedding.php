<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['user_id', 'label', 'photo_path', 'model', 'embedding'])]
class FaceEmbedding extends Model
{
    protected static function booted(): void
    {
        static::deleted(function (self $faceEmbedding): void {
            if ($faceEmbedding->photo_path && Storage::disk('local')->exists($faceEmbedding->photo_path)) {
                Storage::disk('local')->delete($faceEmbedding->photo_path);
            }
        });
    }

    public function hasPhoto(): bool
    {
        return ! empty($this->photo_path);
    }

    /**
     * Jumlah angka pada satu sidik wajah.
     *
     * Nilai ini WAJIB sama dengan FACE_DIMENSIONS di layanan wajah Python
     * (folder instighface/absen-solat). Model InsightFace ArcFace menghasilkan
     * 512 angka; model dlib sebelumnya menghasilkan 128 angka.
     *
     * Angka dari kedua model TIDAK bisa dibandingkan sama sekali, jadi
     * mengubah nilai ini membuat seluruh sidik wajah lama tidak terpakai dan
     * anggota perlu mendaftar ulang wajahnya. Pemeriksaan panjang angka di
     * FaceMatcherService akan melewati baris lama dengan sendirinya, sehingga
     * yang terjadi adalah wajah tidak dikenali, bukan salah mengenali.
     */
    public const Dimensions = 512;

    /**
     * Nama model wajah bawaan yang dipakai mesin pengenal.
     *
     * Nilai ini WAJIB sama dengan FACE_MODEL di berkas .env, sebab
     * FaceMatcherService memakai nama ini untuk memutuskan sidik wajah mana
     * yang boleh dibandingkan. Bila namanya berbeda, seluruh sidik wajah lama
     * dilewati dan tidak ada anggota yang bisa dikenali.
     */
    public const DefaultModel = 'insightface-buffalo_sc';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'embedding' => 'array',
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
     * @param  Builder<FaceEmbedding>  $query
     */
    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    /**
     * Rapikan angka pembanding supaya bentuknya selalu sama sebelum disimpan.
     *
     * @param  array<int, mixed>  $embedding
     * @return array<int, float>
     */
    public static function normalizeEmbedding(array $embedding): array
    {
        return array_map(static fn ($value): float => round((float) $value, 6), array_values($embedding));
    }
}
