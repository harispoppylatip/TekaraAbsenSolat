<?php

namespace App\Services;

use App\Models\FaceEmbedding;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Pencocokan wajah di sisi aplikasi.
 *
 * Angka pembanding dari layanan wajah (512 bilangan) dicocokkan dengan seluruh
 * sidik wajah yang tersimpan. Cara ini disebut pencocokan 1:N: dari satu wajah
 * di depan kamera, dicari siapa di antara semua anggota yang paling mendekati.
 *
 * Yang menentukan di sini bukan mencari yang paling dekat, melainkan memutuskan
 * apakah yang paling dekat itu memang cukup dekat untuk dipercaya. Jarak
 * terkecil selalu ada di antara angka mana pun, jadi menerimanya begitu saja
 * membuat siapa pun yang kebetulan paling mirip tercatat sebagai anggota itu.
 *
 * Keputusan diambil berlapis:
 *   1. jarak dihitung ke setiap foto anggota, lalu dikelompokkan per anggota
 *   2. jarak satu anggota adalah rata-rata beberapa jarak terdekatnya, supaya
 *      satu foto yang buruk tidak menjadi kunci bagi semua orang
 *   3. jarak itu wajib di bawah ambang penerimaan, dan lebih ketat lagi bila
 *      anggota tersebut belum punya cukup foto pendukung
 *   4. bila dua anggota sama-sama dekat, hasilnya dilaporkan ambigu dan tidak
 *      ada identitas yang dipilih
 *
 * Seluruh ambang diatur di config/face.php dan dapat diubah lewat berkas .env.
 */
class FaceMatcherService
{
    /** Identitas berhasil dipastikan. */
    public const StatusMatched = 'matched';

    /** Tidak ada anggota yang cukup mirip. */
    public const StatusUnknown = 'unknown';

    /** Dua anggota sama-sama mirip sehingga tidak boleh dipilih sendiri. */
    public const StatusAmbiguous = 'ambiguous';

    /** Angka wajah yang masuk tidak bisa dipakai. */
    public const StatusInvalid = 'invalid';

    /** Bentuk angka wajah yang masuk tidak sesuai. */
    public const ReasonWrongDimensions = 'wrong_dimensions';

    /** Belum ada angka wajah tersimpan yang bisa dipakai. */
    public const ReasonNoReference = 'no_reference';

    /** Kandidat terdekat masih lebih jauh daripada ambang penerimaan. */
    public const ReasonAboveTolerance = 'above_tolerance';

    /** Selisih dengan anggota kedua terlalu tipis untuk diputuskan. */
    public const ReasonTooCloseToAnother = 'too_close_to_another';

    /**
     * Jumlah angka wajah tersimpan yang dilewati pada pencocokan terakhir,
     * misalnya karena dimensinya salah atau modelnya berbeda.
     */
    private int $skipped = 0;

    /**
     * Cocokkan satu angka wajah dengan seluruh sidik wajah yang tersimpan.
     *
     * `distance` pada balasan adalah angka keputusan: rata-rata beberapa jarak
     * foto terdekat anggota tersebut. `closest` adalah jarak ke satu foto paling
     * mirip, disertakan hanya untuk pemeriksaan dan catatan.
     *
     * @param  array<int, mixed>  $embedding
     * @return array{status: string, user: User|null, distance: float|null, closest: float|null, photos: int, runner_up: User|null, runner_up_distance: float|null, margin: float|null, reason: string|null, skipped: int}
     */
    public function match(array $embedding): array
    {
        $this->skipped = 0;

        if (count($embedding) !== FaceEmbedding::Dimensions) {
            return $this->refusal(self::StatusInvalid, self::ReasonWrongDimensions);
        }

        $groups = $this->groups($embedding);

        if ($groups === []) {
            return $this->refusal(self::StatusUnknown, self::ReasonNoReference);
        }

        $candidates = $this->rank($groups);
        $top = $candidates[0];
        $runnerUp = $candidates[1] ?? null;

        // Anggota yang fotonya lebih sedikit daripada jumlah foto yang dipakai
        // menilai tidak punya foto pendukung, jadi jaraknya wajib lebih dekat.
        $limit = $top['photos'] < $this->aggregatePhotos()
            ? $this->singlePhotoTolerance()
            : $this->tolerance();

        if ($top['distance'] > $limit) {
            return $this->refusal(self::StatusUnknown, self::ReasonAboveTolerance, $top, $runnerUp);
        }

        $margin = $runnerUp === null ? null : $runnerUp['distance'] - $top['distance'];

        // Anggota kedua hanya berarti bila jaraknya juga masih masuk akal.
        // Kalau dia jauh, juara pertama memang tidak ada saingannya.
        if ($margin !== null && $runnerUp['distance'] <= $this->tolerance() && $margin < $this->minMargin()) {
            return [
                'status' => self::StatusAmbiguous,
                'user' => null,
                'distance' => $top['distance'],
                'closest' => $top['closest'],
                'photos' => $top['photos'],
                'runner_up' => $runnerUp['user'],
                'runner_up_distance' => $runnerUp['distance'],
                'margin' => $margin,
                'reason' => self::ReasonTooCloseToAnother,
                'skipped' => $this->skipped,
            ];
        }

        return [
            'status' => self::StatusMatched,
            'user' => $top['user'],
            'distance' => $top['distance'],
            'closest' => $top['closest'],
            'photos' => $top['photos'],
            'runner_up' => $runnerUp['user'] ?? null,
            'runner_up_distance' => $runnerUp['distance'] ?? null,
            'margin' => $margin,
            'reason' => null,
            'skipped' => $this->skipped,
        ];
    }

    /**
     * Bentuk lama pencarian anggota, dipertahankan supaya pemanggil lama tidak
     * ikut berubah. Pemakaian baru sebaiknya memakai `match()` supaya alasannya
     * bisa dibedakan.
     *
     * @param  array<int, mixed>  $embedding
     * @return array{user: User, distance: float}|null
     */
    public function identify(array $embedding): ?array
    {
        $match = $this->match($embedding);

        if ($match['status'] !== self::StatusMatched || $match['user'] === null) {
            return null;
        }

        return ['user' => $match['user'], 'distance' => (float) $match['distance']];
    }

    /**
     * Kumpulkan jarak ke setiap foto yang tersimpan, dikelompokkan per anggota.
     *
     * Baris yang angka wajahnya rusak, dimensinya tidak sama, tidak punya
     * anggota, atau dihitung oleh model lain dilewati dan dihitung supaya
     * operator bisa menelusuri kenapa tidak ada yang cocok.
     *
     * @param  array<int, mixed>  $embedding
     * @return array<int, array{user: User, distances: array<int, float>}>
     */
    private function groups(array $embedding): array
    {
        $model = (string) config('face.model', FaceEmbedding::DefaultModel);
        $enforce = (bool) config('face.enforce_model', true);
        $groups = [];

        FaceEmbedding::query()
            ->with('user')
            ->whereHas('user', fn ($query) => $query->where('enrollment_status', User::EnrollmentCompleted))
            ->chunkById(500, function ($rows) use ($embedding, $model, $enforce, &$groups): void {
                foreach ($rows as $row) {
                    $stored = $row->embedding;

                    if ($row->user === null || ! is_array($stored) || count($stored) !== count($embedding)) {
                        $this->skipped++;

                        continue;
                    }

                    if (! $row->user->hasCompletedEnrollment()) {
                        $this->skipped++;

                        continue;
                    }

                    if ($enforce && $this->otherModel($row, $model)) {
                        $this->skipped++;

                        continue;
                    }

                    $userId = (int) $row->user_id;

                    $groups[$userId] ??= ['user' => $row->user, 'distances' => []];
                    $groups[$userId]['distances'][] = $this->distance($embedding, $stored);
                }
            });

        return $groups;
    }

    /**
     * Ubah kelompok jarak menjadi daftar kandidat yang sudah terurut.
     *
     * @param  array<int, array{user: User, distances: array<int, float>}>  $groups
     * @return array<int, array{user: User, distance: float, closest: float, photos: int}>
     */
    private function rank(array $groups): array
    {
        $take = $this->aggregatePhotos();
        $candidates = [];

        foreach ($groups as $group) {
            $distances = $group['distances'];
            sort($distances);

            $nearest = array_slice($distances, 0, $take);

            $candidates[] = [
                'user' => $group['user'],
                'distance' => array_sum($nearest) / count($nearest),
                'closest' => $distances[0],
                'photos' => count($distances),
            ];
        }

        usort(
            $candidates,
            static fn (array $left, array $right): int => $left['distance'] <=> $right['distance'],
        );

        return $candidates;
    }

    /**
     * Periksa kerukunan foto milik satu anggota.
     *
     * Dipakai halaman Data Wajah untuk menandai foto yang berbeda jauh dari
     * foto lain milik anggota yang sama. Foto seperti itu biasanya wajahnya
     * tidak terbaca jelas, hasil potret layar, atau bahkan orang yang salah,
     * dan kehadirannya membuat anggota tersebut mudah dikenali sebagai orang
     * lain oleh siapa pun.
     *
     * @param  Collection<int, FaceEmbedding>  $embeddings
     * @return array<int, float> jarak terdekat setiap foto ke foto saudaranya
     */
    public function siblingDistances(Collection $embeddings): array
    {
        $rows = $embeddings
            ->filter(fn (FaceEmbedding $row): bool => is_array($row->embedding) && count($row->embedding) === FaceEmbedding::Dimensions)
            ->values();

        $result = [];

        foreach ($rows as $index => $row) {
            $nearest = null;

            foreach ($rows as $other => $candidate) {
                if ($index === $other) {
                    continue;
                }

                $distance = $this->distance($row->embedding, $candidate->embedding);
                $nearest = $nearest === null ? $distance : min($nearest, $distance);
            }

            $result[(int) $row->getKey()] = $nearest ?? 0.0;
        }

        return $result;
    }

    /**
     * Jarak antara dua angka pembanding. Semakin kecil, semakin mirip.
     *
     * @param  array<int, mixed>  $left
     * @param  array<int, mixed>  $right
     */
    public function distance(array $left, array $right): float
    {
        $sum = 0.0;

        foreach ($left as $index => $value) {
            $difference = (float) $value - (float) ($right[$index] ?? 0.0);
            $sum += $difference * $difference;
        }

        return sqrt($sum);
    }

    public function tolerance(): float
    {
        return (float) config('face.tolerance', 0.5);
    }

    public function singlePhotoTolerance(): float
    {
        return (float) config('face.single_photo_tolerance', 0.45);
    }

    public function aggregatePhotos(): int
    {
        return max(1, (int) config('face.aggregate_photos', 2));
    }

    public function minMargin(): float
    {
        return max(0.0, (float) config('face.min_margin', 0.06));
    }

    /**
     * Apakah baris simpanan ini dihitung oleh model wajah yang lain.
     */
    private function otherModel(FaceEmbedding $row, string $model): bool
    {
        $stored = is_string($row->model) ? trim($row->model) : '';

        return $stored !== '' && $stored !== $model;
    }

    /**
     * @param  array{user: User, distance: float, closest: float, photos: int}|null  $top
     * @param  array{user: User, distance: float, closest: float, photos: int}|null  $runnerUp
     * @return array{status: string, user: null, distance: float|null, closest: float|null, photos: int, runner_up: User|null, runner_up_distance: float|null, margin: null, reason: string, skipped: int}
     */
    private function refusal(string $status, string $reason, ?array $top = null, ?array $runnerUp = null): array
    {
        return [
            'status' => $status,
            'user' => null,
            'distance' => $top['distance'] ?? null,
            'closest' => $top['closest'] ?? null,
            'photos' => $top['photos'] ?? 0,
            'runner_up' => $runnerUp['user'] ?? null,
            'runner_up_distance' => $runnerUp['distance'] ?? null,
            'margin' => null,
            'reason' => $reason,
            'skipped' => $this->skipped,
        ];
    }
}
