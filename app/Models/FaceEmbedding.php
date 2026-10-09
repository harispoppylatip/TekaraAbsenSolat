<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'label', 'model', 'embedding'])]
class FaceEmbedding extends Model
{
    /**
     * Jumlah angka pada satu sidik wajah.
     */
    public const Dimensions = 128;

    /**
     * Nama model wajah bawaan yang dipakai mesin pengenal.
     */
    public const DefaultModel = 'dlib-resnet-v1';

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
