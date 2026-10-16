<?php

namespace App\Jobs;

use App\Models\FacePhotoUpload;
use App\Services\FaceEnrollmentService;
use App\Services\FacePhotoQueueService;
use App\Services\StudentEnrollmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Ubah satu foto wajah menjadi 512 angka wajah di luar permintaan web.
 *
 * Satu foto memakan waktu belasan detik di mesin tanpa kartu grafis, jadi
 * pekerjaan ini tidak boleh menahan halaman admin. Halaman Wajah hanya
 * menitipkan berkasnya lalu melempar pekerjaan ini ke antrean; baris
 * FacePhotoUpload yang mencatat kemajuannya.
 */
class EncodeFacePhoto implements ShouldQueue
{
    use Queueable;

    /**
     * Tidak diulang otomatis. Kegagalan harus terlihat admin di panel kemajuan,
     * bukan disembunyikan di belakang percobaan ulang yang tidak terlihat.
     */
    public int $tries = 1;

    /** Satu foto sekitar 15 detik; batas ini hanya pengaman agar tidak menggantung. */
    public int $timeout = 120;

    public function __construct(public int $uploadId) {}

    public function handle(FaceEnrollmentService $faces, FacePhotoQueueService $queue): void
    {
        $row = FacePhotoUpload::query()->with('user')->find($this->uploadId);

        // Baris yang sudah diproses tidak dikerjakan dua kali.
        if ($row === null || $row->status !== FacePhotoUpload::StatusMenunggu) {
            return;
        }

        try {
            $this->process($row, $faces, $queue);
        } finally {
            // Penggantian kumpulan foto diselesaikan setelah baris ini selesai,
            // dan hanya benar benar mengubah data ketika seluruh foto kelompok
            // itu sudah selesai. Dikerjakan di sini, bukan di dalam penyimpanan
            // angkanya, supaya penggantian berlaku pada seluruh kumpulan dan
            // bukan pada satu foto.
            $queue->finaliseReplacement($row, $faces);

            // Keadaan pendaftaran pemilik akun dihitung ulang dari jumlah foto
            // yang benar benar tersimpan. Tanpa ini, satu-satunya tanda bahwa
            // pendaftaran selesai adalah jumlah baris di tabel angka wajah, dan
            // halaman pendaftaran tidak bisa membedakan "sedang diolah" dari
            // "fotonya ditolak".
            $this->refreshEnrollment($row);
        }
    }

    /**
     * Perbarui keadaan pendaftaran pemilik foto.
     */
    private function refreshEnrollment(FacePhotoUpload $row): void
    {
        if ($row->user === null) {
            return;
        }

        if ($row->self_enrolled) {
            app(StudentEnrollmentService::class)->refresh($row->user);
        } else {
            if ($row->status === FacePhotoUpload::StatusBerhasil && ! $row->user->hasCompletedEnrollment()) {
                $row->user->forceFill([
                    'enrollment_status' => \App\Models\User::EnrollmentCompleted,
                    'enrolled_at' => now(),
                ])->save();
            }
        }
    }

    /**
     * Ubah satu foto menjadi angka wajah, lalu tutup baris kemajuannya.
     */
    private function process(FacePhotoUpload $row, FaceEnrollmentService $faces, FacePhotoQueueService $queue): void
    {
        if ($row->user === null) {
            $this->settle($row, FacePhotoUpload::StatusGagal, null, 'Anggota pemilik foto ini sudah tidak ada.');

            return;
        }

        $row->forceFill([
            'status' => FacePhotoUpload::StatusDiproses,
            'started_at' => now(),
        ])->save();

        $disk = Storage::disk('local');

        if (! $disk->exists($row->path)) {
            $this->settle($row, FacePhotoUpload::StatusGagal, null, 'Berkas foto sudah dihapus dari server. Unggah ulang fotonya.');

            return;
        }

        // Foto baru SELALU ditumpuk di sini, termasuk pada kelompok yang meminta
        // penggantian. Foto lama baru dibuang setelah seluruh foto kelompok ini
        // selesai, sehingga foto yang gagal tidak pernah ikut tersimpan dan foto
        // lama tidak pernah hilang karena foto baru yang gagal.
        $mandiri = (bool) $row->self_enrolled;

        $result = $faces->enrollFromContents(
            $row->user,
            $disk->get($row->path),
            $row->label,
            false,
            // Kiriman mandiri diperiksa lebih ketat: mutu fotonya dinilai, dan
            // hasilnya dibandingkan dengan foto yang sudah diterima di kelompok
            // yang sama supaya sepuluh kiriman yang hampir sama persis tidak
            // lolos sebagai sepuluh bahan pembanding.
            $mandiri,
            $mandiri ? $queue->acceptedEmbeddings((string) $row->batch_id) : [],
        );
        $saved = $result['status'] === FaceEnrollmentService::StatusSaved;

        $this->settle(
            $row,
            $saved ? FacePhotoUpload::StatusBerhasil : FacePhotoUpload::StatusGagal,
            $result['status'],
            $result['message'],
            $result['embedding_id'] ?? null,
            $result['status'] === FaceEnrollmentService::StatusUnavailable,
        );
    }

    /**
     * Pekerjaan yang meledak tidak boleh meninggalkan baris menggantung di
     * "sedang diproses", sebab panel kemajuan akan berhenti di angka itu dan
     * admin tidak tahu apa yang terjadi.
     */
    public function failed(?Throwable $error): void
    {
        $row = FacePhotoUpload::query()->find($this->uploadId);

        if ($row === null) {
            return;
        }

        $this->settle($row, FacePhotoUpload::StatusGagal, null, 'Pengolahan foto terhenti karena gangguan di server. Coba lagi sebentar.');

        // Kelompok yang meminta penggantian tetap diselesaikan, karena baris ini
        // baru saja ditutup dan bisa jadi inilah foto terakhir yang ditunggu.
        // Bila masih ada foto lain yang belum selesai, penggantiannya menunggu
        // foto itu, dan bila ada yang gagal di antaranya kumpulan lama tetap
        // dipertahankan.
        app(FacePhotoQueueService::class)->finaliseReplacement($row, app(FaceEnrollmentService::class));
        $this->refreshEnrollment($row);
    }

    private function settle(
        FacePhotoUpload $row,
        string $status,
        ?string $resultStatus,
        string $message,
        ?int $embeddingId = null,
        bool $keepPhoto = false,
    ): void {
        $row->forceFill([
            'status' => $status,
            'result_status' => $resultStatus,
            'message' => $message,
            'face_embedding_id' => $embeddingId,
            'finished_at' => now(),
        ])->save();

        // Berkas foto hanya dipakai sampai angkanya tersimpan, kecuali bila
        // kegagalannya karena layanan wajah mati: fotonya masih benar sehingga
        // bisa dicoba ulang setelah layanan dinyalakan.
        if (! $keepPhoto) {
            Storage::disk('local')->delete($row->path);
        }
    }
}
