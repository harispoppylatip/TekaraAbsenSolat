<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Satu foto wajah yang menunggu diubah menjadi angka wajah.
 *
 * Baris ini adalah satu-satunya tempat kemajuan pendaftaran wajah dibaca, baik
 * oleh panel di halaman Wajah maupun oleh jawaban JSON yang dipantau peramban.
 */
#[Fillable([
    'batch_id',
    'user_id',
    'uploaded_by',
    'original_name',
    'path',
    'label',
    'self_enrolled',
    'replace',
    'status',
    'result_status',
    'message',
    'face_embedding_id',
    'started_at',
    'finished_at',
])]
class FacePhotoUpload extends Model
{
    /** Baru dititipkan dan belum diambil pekerja antrean. */
    public const StatusMenunggu = 'menunggu';

    /** Sedang diubah menjadi angka wajah oleh pekerja antrean. */
    public const StatusDiproses = 'diproses';

    /** Angka wajahnya sudah tersimpan dan berkas fotonya sudah dihapus. */
    public const StatusBerhasil = 'berhasil';

    /** Gagal; berkasnya mungkin masih ada sehingga bisa diulang. */
    public const StatusGagal = 'gagal';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'replace' => 'boolean',
            'self_enrolled' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return BelongsTo<FaceEmbedding, $this>
     */
    public function embedding(): BelongsTo
    {
        return $this->belongsTo(FaceEmbedding::class, 'face_embedding_id');
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::StatusBerhasil, self::StatusGagal], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::StatusBerhasil => 'Tersimpan',
            self::StatusGagal => 'Gagal',
            self::StatusDiproses => 'Sedang diproses',
            default => 'Menunggu antrean',
        };
    }

    /**
     * Nama pendek berkas foto yang ditampilkan di panel kemajuan.
     */
    public function shortName(int $limit = 40): string
    {
        return Str::limit($this->original_name, $limit, '');
    }

    /**
     * Foto gagal masih bisa diulang selama berkasnya belum dihapus. Berkas hanya
     * disimpan bila kegagalannya karena layanan wajah mati, sebab foto seperti
     * itu isinya tidak salah, hanya mesinnya yang belum menyala.
     */
    public function canRetry(): bool
    {
        return $this->status === self::StatusGagal
            && $this->path !== ''
            && Storage::disk('local')->exists($this->path);
    }

    /**
     * Baris yang belum mencapai keadaan akhir.
     *
     * @param  Builder<FacePhotoUpload>  $query
     */
    public function scopeUnfinished(Builder $query): void
    {
        $query->whereIn('status', [self::StatusMenunggu, self::StatusDiproses]);
    }
}
