<?php

namespace App\Services;

use App\Models\FaceEmbedding;
use App\Models\FacePhotoUpload;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

/**
 * Pendaftaran wajah yang dikerjakan sendiri oleh pemilik akun.
 *
 * Sebelum ini seluruh pendaftaran wajah dikerjakan operator dari panel Wajah.
 * Cara itu tidak bisa dipakai saat jumlah anggota mencapai ratusan orang, sebab
 * setiap orang perlu lima sampai sepuluh foto yang diambil satu per satu.
 *
 * Kelas ini hanya mengurus bagian yang KHAS pendaftaran mandiri: keadaan
 * pendaftaran, batas jumlah foto, dan penyusunan kiriman. Penyimpanan angkanya
 * tetap dikerjakan FaceEnrollmentService dan pengolahan latar belakangnya tetap
 * FacePhotoQueueService, supaya tidak ada aturan kedua yang menyimpang dari
 * aturan yang sudah berlaku di panel.
 */
class StudentEnrollmentService
{
    public function __construct(
        private FacePhotoQueueService $queue,
        private FaceEnrollmentService $faces,
    ) {}

    /**
     * Jumlah kelompok unggahan yang ditampilkan di halaman pendaftaran.
     */
    public const BatchesShown = 5;

    /**
     * Jumlah foto yang wajib terkumpul sebelum pendaftaran dianggap selesai.
     */
    public function requiredPhotos(): int
    {
        return max(1, (int) config('face.enrollment.required_photos', 5));
    }

    /**
     * Jumlah foto terbanyak yang boleh dikumpulkan satu anggota.
     *
     * Dibatasi oleh batas foto per anggota, sebab foto di atas batas itu akan
     * langsung dibuang saat disimpan dan pemiliknya merasa fotonya diterima.
     */
    public function maxPhotos(): int
    {
        $ceiling = (int) config('face.max_photos_per_user', 20);
        $wanted = (int) config('face.enrollment.max_photos', 10);

        return max($this->requiredPhotos(), min($wanted, $ceiling));
    }

    public function maxPerSubmit(): int
    {
        return max(1, (int) config('face.enrollment.max_photos_per_submit', 3));
    }

    /**
     * Panduan variasi foto yang ditampilkan di halaman pendaftaran.
     *
     * Jumlah wajib pertama pada daftar ini adalah variasi yang harus ada. Sisa
     * daftarnya bersifat tambahan, dipakai bila pemiliknya ingin menambah foto
     * supaya wajahnya lebih mudah dikenali.
     *
     * @return array<int, array{label: string, hint: string, required: bool}>
     */
    public function variations(): array
    {
        $wajib = $this->requiredPhotos();
        $hasil = [];

        foreach ((array) config('face.enrollment.variations', []) as $index => $variasi) {
            if (! is_array($variasi)) {
                continue;
            }

            $hasil[] = [
                'label' => (string) ($variasi['label'] ?? ''),
                'hint' => (string) ($variasi['hint'] ?? ''),
                'required' => $index < $wajib,
            ];
        }

        return $hasil;
    }

    /**
     * Seluruh keadaan yang dibutuhkan halaman pendaftaran.
     *
     * @return array{
     *     status: string,
     *     label: string,
     *     total: int,
     *     usable: int,
     *     unusable: int,
     *     required: int,
     *     max: int,
     *     max_per_submit: int,
     *     percent: int,
     *     finished: bool,
     *     pending: bool,
     *     has_photos: bool,
     *     batches: array<int, array<string, mixed>>
     * }
     */
    public function state(User $user): array
    {
        $photo = $this->photoBreakdown($user);
        $required = $this->requiredPhotos();
        $pending = $this->hasPending($user);

        return [
            'status' => $user->enrollmentStatus(),
            'label' => $user->enrollmentLabel(),
            'total' => $photo['total'],
            'usable' => $photo['usable'],
            'unusable' => $photo['unusable'],
            'required' => $required,
            'max' => $this->maxPhotos(),
            'max_per_submit' => $this->maxPerSubmit(),
            'percent' => (int) min(100, round(($photo['usable'] / max(1, $required)) * 100)),
            'finished' => $photo['usable'] >= $required && ! $pending,
            'pending' => $pending,
            'has_photos' => $photo['total'] > 0,
            'batches' => $this->queue->batches(self::BatchesShown, $user, false),
        ];
    }

    /**
     * Titipkan foto pendaftaran ke antrean.
     *
     * Kelompok unggahan memakai penanda pendaftaran mandiri supaya pemeriksaan
     * mutu foto dan pemeriksaan foto kembar hanya berlaku di jalur ini.
     *
     * @param  array<int, UploadedFile>  $photos
     * @return array{batch: string, count: int}
     */
    public function submit(User $user, array $photos, bool $replace = false): array
    {
        /*
        | Keadaan "sedang diolah" ditulis SEBELUM fotonya dititipkan, bukan
        | sesudahnya. Antrean bisa berjalan langsung tanpa menunggu (pengaturan
        | `sync`), sehingga menulisnya setelah penitipan akan menimpa keadaan
        | akhir yang baru saja dihitung menjadi "sedang diolah" lagi, dan
        | pemiliknya melihat fotonya seperti tidak pernah selesai diperiksa.
        */
        $this->markProcessing($user);

        return $this->queue->enqueue($user, $photos, null, null, $replace, true);
    }

    /**
     * Apakah masih ada foto anggota ini yang belum selesai diolah.
     *
     * Dipakai untuk mencegah dua proses pendaftaran berjalan bersamaan pada satu
     * akun. Dua kiriman yang jalan bersamaan membuat pemeriksaan foto kembar
     * tidak bisa melihat foto kiriman yang lain, dan pemiliknya melihat kemajuan
     * yang tercampur dari dua kelompok berbeda.
     */
    public function hasPending(User $user): bool
    {
        return FacePhotoUpload::query()
            ->where('user_id', $user->getKey())
            ->unfinished()
            ->exists();
    }

    /**
     * Hapus satu foto wajah milik anggota.
     */
    public function removePhoto(User $user, FaceEmbedding $photo): bool
    {
        if ((int) $photo->user_id !== (int) $user->getKey()) {
            return false;
        }

        $photo->delete();
        $this->refresh($user);

        return true;
    }

    /**
     * Hapus SELURUH foto wajah milik anggota.
     *
     * Dipakai saat pemiliknya ingin memulai dari awal. Data lama baru dibuang
     * setelah ia menekan tombolnya sendiri, bukan otomatis, supaya tidak ada
     * wajah yang hilang tanpa diminta.
     */
    public function removeAll(User $user): int
    {
        $jumlah = FaceEmbedding::query()->forUser((int) $user->getKey())->delete();
        $this->refresh($user);

        return $jumlah;
    }

    /**
     * Ulangi foto yang gagal pada satu kelompok unggahan milik anggota.
     */
    public function retryBatch(User $user, string $batchId): int
    {
        $milikSendiri = FacePhotoUpload::query()
            ->where('batch_id', $batchId)
            ->where('user_id', $user->getKey())
            ->exists();

        if (! $milikSendiri) {
            return 0;
        }

        $retried = $this->queue->retryBatch($batchId);
        $this->refresh($user);

        return $retried;
    }

    /**
     * Tandai bahwa foto sudah dikirim dan sedang diolah.
     *
     * Hanya menaikkan keadaan yang belum selesai. Pendaftaran yang sudah selesai
     * tidak boleh turun kembali hanya karena pemiliknya menambah foto.
     */
    public function markProcessing(User $user): void
    {
        if ($user->hasCompletedEnrollment()) {
            return;
        }

        $this->write($user, User::EnrollmentProcessing);
    }

    /**
     * Perbarui keadaan pendaftaran dari data yang benar benar ada.
     *
     * Keadaannya dihitung ulang, bukan disimpan bertahap, supaya satu sumber
     * kebenaran tetap dimiliki: jumlah foto yang benar benar tersimpan. Dipanggil
     * setelah setiap perubahan data, termasuk setelah pekerja antrean selesai.
     */
    public function refresh(User $user): string
    {
        $photo = $this->photoBreakdown($user);
        $required = $this->requiredPhotos();

        $status = match (true) {
            $this->hasPending($user) => User::EnrollmentProcessing,
            $photo['usable'] >= $required => User::EnrollmentCompleted,
            $photo['usable'] > 0 => User::EnrollmentInProgress,
            $photo['unusable'] > 0 => User::EnrollmentNeedsReenrollment,
            $this->lastBatchFailed($user) => User::EnrollmentRejected,
            default => User::EnrollmentNotStarted,
        };

        $this->write($user, $status);

        return $status;
    }

    /**
     * Jumlah foto wajah anggota, dipisah antara yang bisa dipakai dan yang tidak.
     *
     * "Tidak bisa dipakai" berarti angkanya dihitung model lain atau panjangnya
     * berbeda dari yang berlaku sekarang. Data seperti itu tidak bisa
     * dibandingkan dengan foto baru, tetapi juga tidak boleh dihapus diam-diam,
     * jadi jumlahnya dilaporkan supaya pemiliknya tahu harus mendaftar ulang.
     *
     * @return array{total: int, usable: int, unusable: int}
     */
    public function photoBreakdown(User $user): array
    {
        /** @var Collection<int, FaceEmbedding> $rows */
        $rows = $user->faceEmbeddings()->get(['id', 'embedding', 'model']);
        $model = (string) config('face.model', FaceEmbedding::DefaultModel);

        $usable = $rows->filter(function (FaceEmbedding $row) use ($model): bool {
            $embedding = $row->embedding;

            if (! is_array($embedding) || count($embedding) !== FaceEmbedding::Dimensions) {
                return false;
            }

            $punya = trim((string) $row->model);

            return $punya === '' || $punya === $model;
        })->count();

        return [
            'total' => $rows->count(),
            'usable' => $usable,
            'unusable' => $rows->count() - $usable,
        ];
    }

    /**
     * Apakah kelompok unggahan terakhir anggota ini memuat foto yang gagal.
     */
    private function lastBatchFailed(User $user): bool
    {
        /** @var string|null $batchId */
        $batchId = FacePhotoUpload::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('id')
            ->value('batch_id');

        if ($batchId === null) {
            return false;
        }

        return FacePhotoUpload::query()
            ->where('batch_id', $batchId)
            ->where('status', FacePhotoUpload::StatusGagal)
            ->exists();
    }

    private function write(User $user, string $status): void
    {
        $values = ['enrollment_status' => $status];

        if ($status === User::EnrollmentProcessing && $user->enrollment_started_at === null) {
            $values['enrollment_started_at'] = now();
        }

        if ($status === User::EnrollmentCompleted && $user->enrolled_at === null) {
            $values['enrolled_at'] = now();
        }

        $user->forceFill($values)->save();
    }
}
