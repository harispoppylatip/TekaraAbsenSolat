<?php

namespace App\Services;

use App\Jobs\EncodeFacePhoto;
use App\Models\FaceEmbedding;
use App\Models\FacePhotoUpload;
use App\Models\User;
use App\Support\FacePose;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pintu tunggal unggahan foto wajah yang dikerjakan di latar belakang.
 *
 * Halaman Wajah tidak lagi menunggu mesin wajah. Foto dititipkan ke penyimpanan
 * sementara, satu baris kemajuan dibuat untuk setiap foto, lalu pekerjaannya
 * dimasukkan ke antrean. Kelas ini juga yang menghitung kemajuan, sehingga
 * panel di halaman dan jawaban JSON yang dipantau peramban selalu membaca angka
 * yang sama.
 */
class FacePhotoQueueService
{
    /** Folder penyimpanan sementara foto sebelum diubah menjadi angka wajah. */
    public const Folder = 'antrean-wajah';

    /** Banyak kelompok unggahan yang ditampilkan panel kemajuan. */
    public const BatchesShown = 5;

    /**
     * Lama menunggu sebelum antrean dianggap belum dijalankan. Dipakai untuk
     * memberi tahu admin bahwa pekerja antrean belum hidup.
     */
    public const StallSeconds = 60;

    /**
     * Lama kartu kelompok yang sudah selesai tetap tampil sebelum dihilangkan
     * dari panel kemajuan. Hitungannya dari foto terakhir yang selesai, dan
     * hanya berlaku bila tidak ada pekerjaan yang tersisa sehingga panel tidak
     * penuh kartu lama.
     */
    public const DoneLingerSeconds = 20;

    /**
     * Lama kartu kelompok tetap tampil di halaman pendaftaran wajah setelah
     * seluruh fotonya selesai diperiksa. Berbeda dengan panel admin, aturan
     * ini juga berlaku untuk kelompok yang punya foto gagal: permintaan user
     * 2026-10-15, kartu lama tidak boleh menumpuk saat memotret lagi.
     */
    public const EnrollmentLingerSeconds = 15;

    /**
     * Titipkan beberapa foto satu anggota ke antrean.
     *
     * `$selfEnrolled` menandai foto yang diunggah pemilik akunnya sendiri dari
     * halaman pendaftaran wajah. Foto seperti itu diperiksa mutunya dan
     * dibandingkan dengan foto lain di kelompok yang sama, sedangkan foto yang
     * diunggah operator tidak.
     *
     * @param  array<int, UploadedFile>  $photos
     * @return array{batch: string, count: int}
     */
    public function enqueue(
        User $member,
        array $photos,
        ?User $uploader = null,
        ?string $label = null,
        bool $replace = false,
        bool $selfEnrolled = false,
    ): array {
        $batch = (string) Str::uuid();
        $count = 0;

        foreach ($photos as $photo) {
            if ($photo instanceof UploadedFile) {
                $this->enqueueOne($member, $photo, $batch, $uploader, $label, $replace, $selfEnrolled);
                $count++;
            }
        }

        return ['batch' => $batch, 'count' => $count];
    }

    /**
     * Titipkan satu foto ke antrean lalu jalankan pekerjaannya di latar belakang.
     */
    public function enqueueOne(
        User $member,
        UploadedFile $photo,
        ?string $batch = null,
        ?User $uploader = null,
        ?string $label = null,
        bool $replace = false,
        bool $selfEnrolled = false,
    ): FacePhotoUpload {
        $path = (string) $photo->store(self::Folder, 'local');

        try {
            $row = FacePhotoUpload::create([
                'batch_id' => $batch ?? (string) Str::uuid(),
                'user_id' => $member->getKey(),
                'uploaded_by' => $uploader?->getKey(),
                'original_name' => Str::limit($photo->getClientOriginalName(), 120, ''),
                'path' => $path,
                'label' => $label ?? $photo->getClientOriginalName(),
                'self_enrolled' => $selfEnrolled,
                'replace' => $replace,
                'status' => FacePhotoUpload::StatusMenunggu,
            ]);
        } catch (Throwable $error) {
            // Berkas yang sudah dipindahkan tidak boleh tertinggal tanpa baris
            // kemajuan, sebab tidak ada lagi yang tahu berkas itu milik siapa
            // dan tidak ada yang akan membersihkannya.
            Storage::disk('local')->delete($path);

            throw $error;
        }

        EncodeFacePhoto::dispatch((int) $row->getKey());

        return $row;
    }

    /**
     * Angka wajah yang sudah diterima dari satu kelompok unggahan.
     *
     * Dipakai pemeriksaan foto kembar. Pembandingnya sengaja hanya foto dari
     * kelompok yang SAMA, bukan seluruh wajah anggota itu: yang dicegah adalah
     * sepuluh foto yang hampir sama persis dalam satu kali pendaftaran, bukan
     * anggota yang memperbarui wajahnya dengan sudut baru.
     *
     * @return array<int, array<int, float>>
     */
    public function acceptedEmbeddings(string $batchId): array
    {
        $ids = FacePhotoUpload::query()
            ->where('batch_id', $batchId)
            ->whereNotNull('face_embedding_id')
            ->pluck('face_embedding_id')
            ->all();

        if ($ids === []) {
            return [];
        }

        return FaceEmbedding::query()
            ->whereKey($ids)
            ->pluck('embedding')
            ->map(fn ($embedding): array => is_array($embedding) ? $embedding : [])
            ->reject(fn (array $embedding): bool => $embedding === [])
            ->values()
            ->all();
    }

    /**
     * Selesaikan penggantian foto satu kelompok unggahan.
     *
     * Dipanggil setiap satu foto kelompok itu selesai diproses, tetapi baru
     * benar benar mengubah data setelah SELURUH fotonya selesai. Urutannya
     * sengaja begitu: seluruh foto baru disimpan lebih dulu, baru kumpulan lama
     * dibuang sekali. Kalau penggantian dikerjakan per foto, setiap foto baru
     * menghapus foto baru sebelumnya sehingga hanya satu yang tersisa, dan foto
     * lama bisa hilang walaupun foto barunya gagal.
     *
     * Aman dipanggil berulang kali: penghapusannya idempoten, dan kelompok yang
     * tidak meminta penggantian tidak disentuh sama sekali.
     *
     * @return array{done: bool, kept: int, removed: int}
     */
    public function finaliseReplacement(FacePhotoUpload $finished, FaceEnrollmentService $faces): array
    {
        $nothing = ['done' => false, 'kept' => 0, 'removed' => 0];

        if (! $finished->replace) {
            return $nothing;
        }

        $rows = $this->rowsOfBatch((string) $finished->batch_id);

        // Masih ada foto kelompok ini yang belum selesai: tunggu. Menghapus
        // lebih awal berarti membuang data lama padahal foto barunya belum
        // tentu berhasil.
        if ($rows->contains(fn (FacePhotoUpload $row): bool => ! $row->isFinished())) {
            return $nothing;
        }

        /** @var User|null $member */
        $member = $finished->user ?? User::query()->find($finished->user_id);

        if ($member === null) {
            return $nothing;
        }

        $kept = $rows
            ->filter(fn (FacePhotoUpload $row): bool => $row->face_embedding_id !== null)
            ->pluck('face_embedding_id')
            ->all();

        // Seluruh foto baru gagal: kumpulan lama dipertahankan apa adanya.
        if ($kept === []) {
            return $nothing;
        }

        return [
            'done' => true,
            'kept' => count($kept),
            'removed' => $faces->replaceAllExcept($member, $kept),
        ];
    }

    /**
     * Ulangi satu foto yang gagal selama berkasnya masih tersimpan.
     */
    public function retry(FacePhotoUpload $row): bool
    {
        if (! $row->canRetry()) {
            return false;
        }

        $row->forceFill([
            'status' => FacePhotoUpload::StatusMenunggu,
            'result_status' => null,
            'message' => null,
            'started_at' => null,
            'finished_at' => null,
        ])->save();

        EncodeFacePhoto::dispatch((int) $row->getKey());

        return true;
    }

    /**
     * Ulangi semua foto yang gagal pada satu kelompok unggahan.
     */
    public function retryBatch(string $batchId): int
    {
        $retried = 0;

        foreach ($this->rowsOfBatch($batchId) as $row) {
            if ($row->status === FacePhotoUpload::StatusGagal && $this->retry($row)) {
                $retried++;
            }
        }

        return $retried;
    }

    public function hasUnfinished(): bool
    {
        return FacePhotoUpload::query()->unfinished()->exists();
    }

    /**
     * Ringkasan kemajuan beberapa kelompok unggahan terakhir.
     *
     * `$member` membatasi pembacaan pada satu anggota, dipakai halaman
     * pendaftaran wajah supaya pemiliknya hanya melihat kelompok unggahannya
     * sendiri dan bukan seluruh antrean sekolah.
     *
     * `$hideFinished` mematikan penghilangan kelompok yang sudah selesai.
     * Bawaannya menghilangkan, dan itu yang dipakai panel admin supaya panelnya
     * tidak penuh kartu lama. Halaman pendaftaran wajah mematikannya, sebab
     * pemilik akun perlu masih bisa MEMBACA alasan fotonya ditolak walau
     * pemeriksaannya sudah lewat beberapa menit. Tanpa itu, panelnya berubah
     * menjadi "belum ada foto yang dikirim" padahal fotonya baru saja ditolak,
     * dan pemiliknya tidak tahu apa yang harus diperbaiki.
     *
     * @return array<int, array<string, mixed>>
     */
    public function batches(
        int $limit = self::BatchesShown,
        ?User $member = null,
        bool $hideFinished = true,
    ): array {
        $batchIds = FacePhotoUpload::query()
            ->when($member !== null, fn ($query) => $query->where('user_id', $member->getKey()))
            ->orderByDesc('id')
            ->limit(200)
            ->pluck('batch_id')
            ->unique()
            ->take($limit)
            ->values()
            ->all();

        if ($batchIds === []) {
            return [];
        }

        $grouped = FacePhotoUpload::query()
            ->with('user')
            ->whereIn('batch_id', $batchIds)
            ->orderBy('id')
            ->get()
            ->groupBy('batch_id');

        $batches = [];

        foreach ($batchIds as $batchId) {
            /** @var Collection<int, FacePhotoUpload> $rows */
            $rows = $grouped->get($batchId) ?? collect();

            if ($rows->isEmpty()) {
                continue;
            }

            $removal = $hideFinished ? $this->removalMoment($rows) : null;

            if ($removal !== null && now()->greaterThanOrEqualTo($removal)) {
                continue;
            }

            $batches[] = $this->summarise((string) $batchId, $rows);
        }

        return $batches;
    }

    /**
     * Kelompok unggahan yang ditampilkan di halaman pendaftaran wajah.
     *
     * Hanya kelompok TERBARU milik anggota itu yang dilaporkan, jadi kiriman
     * baru otomatis menggantikan kartu lama. Kartunya hilang
     * EnrollmentLingerSeconds detik setelah seluruh fotonya selesai, termasuk
     * bila ada foto yang gagal. Wilayah `data-live` di halaman membangun ulang
     * isinya dari jawaban ini, jadi penghilangannya WAJIB diputuskan di server:
     * menghapus kartu lewat JavaScript saja akan dibatalkan penyegaran berikutnya.
     *
     * @return array<int, array<string, mixed>>
     */
    public function latestBatchFor(User $member): array
    {
        /** @var string|null $batchId */
        $batchId = FacePhotoUpload::query()
            ->where('user_id', $member->getKey())
            ->orderByDesc('id')
            ->value('batch_id');

        if ($batchId === null) {
            return [];
        }

        $rows = $this->rowsOfBatch($batchId);

        if ($rows->isEmpty()) {
            return [];
        }

        $belumSelesai = $rows->contains(fn (FacePhotoUpload $row): bool => $row->finished_at === null);

        if (! $belumSelesai) {
            /** @var Carbon|null $terakhir */
            $terakhir = $rows->map(fn (FacePhotoUpload $row) => $row->finished_at)->filter()->max();

            if ($terakhir !== null && now()->greaterThanOrEqualTo($terakhir->copy()->addSeconds(self::EnrollmentLingerSeconds))) {
                return [];
            }
        }

        return [$this->summarise($batchId, $rows)];
    }

    /**
     * Saat kartu kelompok ini boleh dihilangkan dari panel kemajuan.
     *
     * Kartu dipertahankan selama masih ada foto yang dikerjakan, dan selama
     * masih ada foto gagal yang berkasnya belum dibuang sehingga tombol Ulangi
     * tetap bisa ditekan. Bila tidak ada pekerjaan yang tersisa, kartunya
     * hilang setelah waktu tunggu terhitung dari foto terakhir yang selesai.
     *
     * @param  Collection<int, FacePhotoUpload>  $rows
     */
    private function removalMoment(Collection $rows): ?Carbon
    {
        $pending = $rows->contains(
            fn (FacePhotoUpload $row): bool => $row->finished_at === null || $row->canRetry()
        );

        if ($pending) {
            return null;
        }

        $lastFinished = $rows->map(fn (FacePhotoUpload $row) => $row->finished_at)->filter()->max();

        /** @var Carbon|null $lastFinished */
        return $lastFinished?->copy()->addSeconds(self::DoneLingerSeconds);
    }

    /**
     * Buang berkas dan catatan antrean yang sudah lama supaya foto sementara
     * tidak menumpuk di server.
     *
     * Dua jenis baris dibersihkan: baris yang sudah selesai dan lewat masa
     * simpan, serta baris yang masih tertulis sedang diproses padahal pekerja
     * antreannya sudah lama mati. Baris jenis kedua tidak punya akhir riwayat
     * sama sekali, jadi tanpa pembersihan ini berkas fotonya akan tersimpan
     * selamanya.
     *
     * @return array{rows: int, files: int}
     */
    public function purge(int $days = 7): array
    {
        $cutoff = now()->subDays(max(1, $days));

        $settled = FacePhotoUpload::query()
            ->where('status', '!=', FacePhotoUpload::StatusDiproses)
            ->where(function ($query) use ($cutoff): void {
                $query->where('finished_at', '<=', $cutoff)
                    ->orWhere(fn ($inner) => $inner->whereNull('finished_at')->where('created_at', '<=', $cutoff));
            })
            ->pluck('id');

        $abandoned = FacePhotoUpload::query()
            ->where('status', FacePhotoUpload::StatusDiproses)
            ->where('started_at', '<=', $cutoff)
            ->pluck('id');

        $rows = FacePhotoUpload::query()
            ->whereKey($settled->merge($abandoned)->all())
            ->get();

        if ($rows->isEmpty()) {
            return ['rows' => 0, 'files' => 0];
        }

        $files = 0;

        foreach ($rows as $row) {
            if (Storage::disk('local')->delete($row->path)) {
                $files++;
            }
        }

        FacePhotoUpload::query()->whereKey($rows->modelKeys())->delete();

        return ['rows' => $rows->count(), 'files' => $files];
    }

    /**
     * @return Collection<int, FacePhotoUpload>
     */
    private function rowsOfBatch(string $batchId): Collection
    {
        return FacePhotoUpload::query()->with('user')->where('batch_id', $batchId)->orderBy('id')->get();
    }

    /**
     * @param  Collection<int, FacePhotoUpload>  $rows
     * @return array<string, mixed>
     */
    private function summarise(string $batchId, Collection $rows): array
    {
        $expected = max(1, (int) config('face.expected_seconds', 15));
        $member = $rows->first()?->user;

        $total = $rows->count();
        $saved = $this->countByStatus($rows, FacePhotoUpload::StatusBerhasil);
        $failed = $this->countByStatus($rows, FacePhotoUpload::StatusGagal);
        $waiting = $this->countByStatus($rows, FacePhotoUpload::StatusMenunggu);
        $processing = $this->countByStatus($rows, FacePhotoUpload::StatusDiproses);
        $retryable = $rows->filter(fn (FacePhotoUpload $row): bool => $row->canRetry())->count();

        $share = $rows
            ->filter(fn (FacePhotoUpload $row): bool => $row->status === FacePhotoUpload::StatusDiproses)
            ->sum(fn (FacePhotoUpload $row): float => $this->runningShare($row, $expected));

        $finished = $saved + $failed;
        $percent = $total === 0 ? 0 : (int) round((($finished + $share) / $total) * 100);
        $percent = max(0, min(100, $percent));

        $stalled = $rows->contains(fn (FacePhotoUpload $row): bool => $row->status === FacePhotoUpload::StatusMenunggu
            && $row->created_at !== null
            && $row->created_at->diffInSeconds(now()) > self::StallSeconds);

        return [
            'id' => $batchId,
            'member' => $member?->name ?? 'Anggota terhapus',
            'member_note' => 'Nomor induk '.($member?->identifier_number ?: 'belum ada')
                .($member?->class_name ? ', kelas '.$member->class_name : ''),
            'identifier' => $member?->identifier_number,
            'class_name' => $member?->class_name,
            'total' => $total,
            'finished' => $finished,
            'saved' => $saved,
            'failed' => $failed,
            'waiting' => $waiting,
            'processing' => $processing,
            'percent' => $percent,
            'done' => $finished >= $total,
            'stalled' => $stalled && $finished < $total,
            'retryable' => $retryable,
            'remove_at' => $this->removalMoment($rows)?->toIso8601String(),
            'replacement' => $this->replacementState($rows, $finished >= $total, $saved),
            'note' => $this->batchNote($total, $finished, $saved, $failed, $waiting, $processing)
                .$this->replacementNote($rows, $finished >= $total, $saved),
            'rows' => $rows
                ->map(fn (FacePhotoUpload $row): array => $this->rowSummary($row, $expected))
                ->values()
                ->all(),
        ];
    }

    private function batchNote(int $total, int $finished, int $saved, int $failed, int $waiting, int $processing): string
    {
        if ($finished >= $total) {
            return 'Selesai. '.$saved.' foto tersimpan'.($failed > 0 ? ', '.$failed.' foto gagal' : '').'.';
        }

        $parts = [$finished.' dari '.$total.' foto selesai'];

        if ($processing > 0) {
            $parts[] = $processing.' foto sedang berjalan';
        }

        if ($waiting > 0) {
            $parts[] = $waiting.' foto menunggu antrean';
        }

        return implode(', ', $parts).'.';
    }

    /**
     * Keadaan penggantian kumpulan foto satu kelompok unggahan.
     *
     * `pending` berarti masih ada fotonya yang diproses, `replaced` berarti
     * kumpulan lama sudah dibuang dan diganti seluruh foto baru yang berhasil,
     * `skipped` berarti tidak ada satu pun foto baru yang berhasil sehingga foto
     * lama sengaja dipertahankan, dan `none` berarti penggantian tidak diminta.
     *
     * @param  Collection<int, FacePhotoUpload>  $rows
     */
    private function replacementState(Collection $rows, bool $finished, int $saved): string
    {
        if (! $rows->contains(fn (FacePhotoUpload $row): bool => $row->replace)) {
            return 'none';
        }

        if (! $finished) {
            return 'pending';
        }

        return $saved > 0 ? 'replaced' : 'skipped';
    }

    /**
     * Keterangan penggantian untuk dibaca admin di panel kemajuan.
     *
     * @param  Collection<int, FacePhotoUpload>  $rows
     */
    private function replacementNote(Collection $rows, bool $finished, int $saved): string
    {
        return match ($this->replacementState($rows, $finished, $saved)) {
            'replaced' => ' Foto lama anggota ini diganti dengan seluruh foto baru yang berhasil.',
            'skipped' => ' Penggantian tidak dilakukan karena tidak ada foto baru yang berhasil, foto lama tetap dipakai.',
            default => '',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function rowSummary(FacePhotoUpload $row, int $expected): array
    {
        $running = $row->status === FacePhotoUpload::StatusDiproses;
        $percent = match ($row->status) {
            FacePhotoUpload::StatusBerhasil, FacePhotoUpload::StatusGagal => 100,
            FacePhotoUpload::StatusDiproses => (int) round($this->runningShare($row, $expected) * 100),
            default => 0,
        };

        $note = match ($row->status) {
            FacePhotoUpload::StatusBerhasil => $row->message ?: 'Tersimpan',
            FacePhotoUpload::StatusGagal => 'Gagal. '.($row->message ?: 'Pengolahan foto tidak berhasil.'),
            FacePhotoUpload::StatusDiproses => 'Sedang berjalan, perkiraan '.$percent.'%',
            default => 'Menunggu antrean',
        };

        return [
            'id' => (int) $row->getKey(),
            'name' => $row->shortName(),
            'pose_label' => FacePose::humanLabel($row->shortName()),
            'pose_label' => \App\Support\FacePose::humanLabel($row->shortName()),
            'status' => $row->status,
            'note' => $note,
            'percent' => $running ? $percent : ($row->isFinished() ? 100 : 0),
            'can_retry' => $row->canRetry(),
        ];
    }

    /**
     * Bagian pekerjaan satu foto yang sudah berjalan, dipakai sebagai
     * sumbangan persen selama foto itu belum selesai.
     */
    private function runningShare(FacePhotoUpload $row, int $expected): float
    {
        if ($row->status !== FacePhotoUpload::StatusDiproses || $row->started_at === null) {
            return 0.0;
        }

        $elapsed = abs((int) round($row->started_at->diffInSeconds(now())));

        // Ditahan di 95 persen supaya bar tidak pernah menampilkan angka penuh
        // padahal angkanya belum tersimpan.
        return min(0.95, $elapsed / $expected);
    }

    /**
     * @param  Collection<int, FacePhotoUpload>  $rows
     */
    private function countByStatus(Collection $rows, string $status): int
    {
        return $rows->filter(fn (FacePhotoUpload $row): bool => $row->status === $status)->count();
    }
}
