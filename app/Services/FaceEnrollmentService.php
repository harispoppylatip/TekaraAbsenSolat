<?php

namespace App\Services;

use App\Models\FaceEmbedding;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pintu tunggal pendaftaran sidik wajah.
 *
 * Ada dua jalan masuk yang menyimpan sidik wajah: perangkat kamera lewat API,
 * dan halaman Wajah di panel yang fotonya diunggah langsung. Keduanya harus
 * memperlakukan data dengan aturan yang sama, jadi aturannya dikumpulkan di
 * sini: batas jumlah foto per anggota, ganti atau tumpuk, dan penolakan foto
 * yang bukan wajah tunggal.
 */
class FaceEnrollmentService
{
    /** Satu foto berhasil disimpan. */
    public const StatusSaved = 'saved';

    /** Tidak ada wajah yang terbaca di foto. */
    public const StatusNoFace = 'no_face';

    /** Wajah di foto lebih dari satu, jadi tidak boleh dipakai mendaftar. */
    public const StatusMultipleFaces = 'multiple_faces';

    /** Wajah di foto terlalu kecil untuk dijadikan pembanding. */
    public const StatusFaceTooSmall = 'face_too_small';

    /** Layanan wajah tidak bisa dihubungi. */
    public const StatusUnavailable = 'service_unavailable';

    /** Foto tidak bisa dibaca mesin wajah. */
    public const StatusInvalid = 'invalid_photo';

    /** Foto terlalu gelap untuk dijadikan pembanding wajah. */
    public const StatusTooDark = 'too_dark';

    /** Foto terlalu terang sampai wajahnya sulit dibaca. */
    public const StatusTooBright = 'too_bright';

    /** Foto kurang tajam, biasanya karena kamera bergerak. */
    public const StatusBlurry = 'blurry';

    /** Wajah menghadap terlalu jauh dari kamera. */
    public const StatusBadPose = 'bad_pose';

    /** Foto hampir sama dengan foto yang sudah diterima di kelompok yang sama. */
    public const StatusDuplicate = 'duplicate';

    public function __construct(
        private FaceEncodingService $encoder,
        private FaceMatcherService $matcher,
    ) {}

    /**
     * Simpan angka pembanding wajah pada baris baru.
     *
     * Bila `replace` benar, seluruh sidik wajah anggota itu diganti baris baru
     * ini. Bila tidak, baris baru ditumpuk dan yang paling lama dibuang begitu
     * jumlahnya melewati batas. Ganti dan tambah dikerjakan dalam satu
     * transaksi supaya data lama tidak sempat terhapus kalau penyimpanan barunya
     * gagal.
     *
     * `replace` di sini hanya untuk satu foto, misalnya kiriman alat kamera yang
     * memang mengganti wajah anggota dengan satu potret baru. Penggantian
     * BANYAK foto tidak boleh memakai cara ini, sebab setiap foto baru akan
     * menghapus foto baru sebelumnya dan hanya satu yang tersisa; kelompok
     * unggahan memakai `replaceAllExcept()` setelah seluruh fotonya selesai.
     *
     * @param  array<int, mixed>  $embedding
     */
    public function store(
        User $user,
        array $embedding,
        ?string $label = null,
        bool $replace = false,
        ?string $photoPath = null,
    ): FaceEmbedding {
        $max = (int) config('face.max_photos_per_user', 20);

        return DB::transaction(function () use ($user, $embedding, $label, $replace, $max, $photoPath): FaceEmbedding {
            $row = FaceEmbedding::create([
                'user_id' => $user->id,
                'embedding' => FaceEmbedding::normalizeEmbedding($embedding),
                'label' => $label,
                'photo_path' => $photoPath,
                'model' => (string) config('face.model', FaceEmbedding::DefaultModel),
            ]);

            if ($replace) {
                FaceEmbedding::query()
                    ->forUser((int) $user->id)
                    ->whereKeyNot($row->getKey())
                    ->get()
                    ->each->delete();
            }

            $extra = FaceEmbedding::query()
                ->forUser((int) $user->id)
                ->orderByDesc('id')
                ->pluck('id')
                ->slice($max)
                ->all();

            if ($extra !== []) {
                FaceEmbedding::query()
                    ->whereKey($extra)
                    ->get()
                    ->each->delete();
            }

            return $row;
        });
    }

    /**
     * Baca sebuah foto dari unggahan halaman, lalu simpan sidik wajahnya.
     *
     * @return array{status: string, message: string, total: int, embedding_id?: int}
     */
    public function enrollFromUpload(User $user, UploadedFile $photo, ?string $label = null, bool $replace = false): array
    {
        return $this->enrollFromContents($user, $this->readPhoto($photo), $label ?? $photo->getClientOriginalName(), $replace);
    }

    /**
     * Baca isi berkas foto, dinilai mesin wajah, lalu disimpan.
     *
     * Isi berkas, bukan unggahan, supaya pekerja antrean bisa memakai jalur yang
     * sama dengan unggahan langsung dari halaman.
     *
     * Dua pilihan terakhir hanya dinyalakan oleh halaman pendaftaran wajah
     * mandiri. Pendaftaran yang dilakukan operator di panel tetap memakai
     * perilaku lama supaya pekerjaan yang sudah berjalan tidak ikut berubah
     * hanya karena ambang mutu atau ambang foto kembar disetel.
     *
     * @param  array<int, array<int, float>>  $compareWith  angka wajah yang sudah diterima di kelompok yang sama
     * @return array{status: string, message: string, total: int, embedding_id?: int}
     */
    public function enrollFromContents(
        User $user,
        string $contents,
        ?string $label = null,
        bool $replace = false,
        bool $checkQuality = false,
        array $compareWith = [],
    ): array {
        $encoded = $checkQuality
            ? $this->encoder->encodeForEnrollment($contents)
            : $this->encoder->encodeContents($contents);

        $before = $this->photoCount($user);
        $max = (int) config('face.max_photos_per_user', 20);

        // Foto berisi lebih dari satu orang tidak dipakai, karena orang yang
        // tidak diniatkan bisa terdaftar dan ikut dikenali saat absen. Mesin
        // wajah yang sudah memperbarui diri menolaknya sendiri; pemeriksaan ini
        // tetap ada untuk mesin wajah versi lama.
        if ($encoded['status'] === FaceEncodingService::StatusMultipleFaces || ($encoded['face_count'] ?? 1) > 1) {
            return $this->multipleFaces($before);
        }

        if ($encoded['status'] !== FaceEncodingService::StatusSuccess) {
            return [
                'status' => $this->translateStatus($encoded['status']),
                'message' => $encoded['message'],
                'total' => $before,
            ];
        }

        // Foto kembar diperiksa SETELAH mutu dan SEBELUM penyimpanan: sepuluh
        // foto yang hampir sama persis tidak menambah bahan pembanding apa pun,
        // hanya membuat pendaftar merasa sudah mengirim sepuluh foto berbeda.
        if ($compareWith !== [] && $this->looksLikeDuplicate($encoded['encoding'], $compareWith)) {
            return [
                'status' => self::StatusDuplicate,
                'message' => 'Foto ini hampir sama dengan foto yang sudah diterima. Ambil dari sudut, jarak, atau arah yang berbeda.',
                'total' => $before,
            ];
        }

        $photoPath = null;
        if ($contents !== '') {
            $dir = 'face-photos/'.$user->getKey();
            $filename = Str::random(40).'.jpg';
            $photoPath = $dir.'/'.$filename;
            Storage::disk('local')->put($photoPath, $contents);
        }

        try {
            $row = $this->store($user, $encoded['encoding'], $this->cleanLabel($label), $replace, $photoPath);

            if (! $checkQuality && ! $user->hasCompletedEnrollment()) {
                $user->forceFill([
                    'enrollment_status' => User::EnrollmentCompleted,
                    'enrolled_at' => now(),
                ])->save();
            }
        } catch (\Throwable $e) {
            if ($photoPath !== null && Storage::disk('local')->exists($photoPath)) {
                Storage::disk('local')->delete($photoPath);
            }
            throw $e;
        }

        $total = $this->photoCount($user);
        $dropped = $before + 1 - $total;

        return [
            'status' => self::StatusSaved,
            'message' => 'Wajah '.$user->name.' tersimpan dari foto '.($row->label ?: 'tanpa nama').'.'
                .($dropped > 0 ? ' Foto terlama dihapus karena batas '.$max.' foto per anggota.' : '')
                .' Total '.$total.' foto.',
            'total' => $total,
            'embedding_id' => (int) $row->getKey(),
        ];
    }

    public function photoCount(User $user): int
    {
        return $user->faceEmbeddings()->count();
    }

    /**
     * Buang seluruh sidik wajah anggota kecuali baris yang diminta dipertahankan.
     *
     * Dipakai penggantian kumpulan foto. Baris yang dipertahankan dikirim dari
     * luar karena hanya pemanggil yang tahu foto mana saja yang benar benar
     * berhasil disimpan dari kelompok unggahan itu.
     *
     * Bila daftar yang dipertahankan kosong, tidak ada yang dihapus. Penggantian
     * yang tidak menghasilkan satu pun foto baru bukan alasan untuk membuang
     * data lama, jadi keadaan itu harus ditolak lebih dulu oleh pemanggil.
     *
     * @param  array<int, int|string|null>  $keepIds
     * @return int jumlah baris yang dibuang
     */
    public function replaceAllExcept(User $user, array $keepIds): int
    {
        $keep = [];

        foreach ($keepIds as $id) {
            $clean = (int) $id;

            if ($clean > 0) {
                $keep[] = $clean;
            }
        }

        if ($keep === []) {
            return 0;
        }

        $rows = FaceEmbedding::query()
            ->forUser((int) $user->getKey())
            ->whereKeyNot($keep)
            ->get();

        $count = $rows->count();
        $rows->each->delete();

        return $count;
    }

    /**
     * Sisa jatah foto wajah anggota ini sebelum menyentuh batas.
     */
    public function remaining(User $user): int
    {
        return max(0, (int) config('face.max_photos_per_user', 20) - $this->photoCount($user));
    }

    private function translateStatus(string $status): string
    {
        return match ($status) {
            FaceEncodingService::StatusNoFace => self::StatusNoFace,
            FaceEncodingService::StatusMultipleFaces => self::StatusMultipleFaces,
            FaceEncodingService::StatusFaceTooSmall => self::StatusFaceTooSmall,
            FaceEncodingService::StatusUnavailable => self::StatusUnavailable,
            FaceEncodingService::StatusTooDark => self::StatusTooDark,
            FaceEncodingService::StatusTooBright => self::StatusTooBright,
            FaceEncodingService::StatusBlurry => self::StatusBlurry,
            FaceEncodingService::StatusBadPose => self::StatusBadPose,
            default => self::StatusInvalid,
        };
    }

    /**
     * Apakah angka wajah ini hampir sama dengan salah satu angka pembanding.
     *
     * Jaraknya memakai jarak yang sama dengan pencocokan absen, supaya satu
     * pengertian "dekat" berlaku di seluruh aplikasi.
     *
     * @param  array<int, mixed>  $encoding
     * @param  array<int, array<int, float>>  $compareWith
     */
    private function looksLikeDuplicate(array $encoding, array $compareWith): bool
    {
        $tolerance = (float) config('face.enrollment.duplicate_tolerance', 0.38);

        if ($tolerance <= 0) {
            return false;
        }

        $baru = FaceEmbedding::normalizeEmbedding($encoding);

        foreach ($compareWith as $lama) {
            if ($this->matcher->distance($baru, FaceEmbedding::normalizeEmbedding($lama)) < $tolerance) {
                return true;
            }
        }

        return false;
    }

    /**
     * Balasan untuk foto yang memuat lebih dari satu wajah.
     *
     * @return array{status: string, message: string, total: int}
     */
    private function multipleFaces(int $before): array
    {
        return [
            'status' => self::StatusMultipleFaces,
            'message' => 'Foto ini memuat lebih dari satu wajah, jadi tidak dipakai. Pilih foto yang hanya berisi satu orang.',
            'total' => $before,
        ];
    }

    private function readPhoto(UploadedFile $photo): string
    {
        $contents = @file_get_contents($photo->getRealPath());

        return is_string($contents) ? $contents : '';
    }

    private function cleanLabel(?string $label): ?string
    {
        $clean = Str::squish((string) $label);

        return $clean === '' ? null : Str::limit($clean, 50, '');
    }
}
