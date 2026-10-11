<?php

namespace App\Services;

use App\Models\SolatAttendanceLog;
use App\Models\SolatSession;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Penyimpan foto bukti absen.
 *
 * Berkasnya diletakkan di cakram privat aplikasi dengan susunan
 * `bukti-absen/tahun/bulan`, dan yang dicatat di basis data hanya lokasinya.
 * Foto tidak pernah bisa dibuka langsung dari peramban: selalu lewat rute
 * berizin supaya hanya pemiliknya, petugas, dan admin yang bisa melihat.
 */
class SolatProofService
{
    public const Disk = 'local';

    public const Folder = 'bukti-absen';

    /**
     * Simpan satu foto bukti dan kembalikan lokasinya.
     *
     * Bila penulisan berkas gagal, hasilnya null. Pemanggil tidak boleh
     * menggagalkan absen hanya karena buktinya tidak tersimpan.
     */
    public function store(string $jpeg, SolatSession $session, CarbonInterface $at): ?string
    {
        $moment = Carbon::parse($at);
        $prayer = Str::slug((string) $session->prayer_name) ?: 'solat';
        $path = self::Folder.'/'.$moment->format('Y/m').'/'
            .$moment->format('Ymd-His').'-'.$prayer.'-'.Str::lower(Str::random(12)).'.jpg';

        return Storage::disk(self::Disk)->put($path, $jpeg) ? $path : null;
    }

    /**
     * Lokasi berkas bukti pada sebuah catatan absen, bila ada.
     */
    public function path(?SolatAttendanceLog $log): ?string
    {
        $path = $log?->proof_photo_path;

        return is_string($path) && $path !== '' ? $path : null;
    }

    /**
     * Berapa catatan absen yang memakai berkas bukti ini.
     *
     * Satu foto frame bisa dipakai bersama oleh beberapa anggota yang absen
     * pada saat yang sama, jadi berkasnya TIDAK boleh dihapus selama masih ada
     * catatan yang menunjuk ke sana.
     *
     * `$exceptLogId` dipakai saat berkas akan dipakai ulang oleh satu catatan
     * tertentu, sebab catatan itu sendiri masih menunjuk ke berkasnya pada
     * saat pemeriksaan.
     */
    public function references(string $path, ?int $exceptLogId = null): int
    {
        if ($path === '') {
            return 0;
        }

        $query = SolatAttendanceLog::query()->where('proof_photo_path', $path);

        if ($exceptLogId !== null) {
            $query->whereKeyNot($exceptLogId);
        }

        return $query->count();
    }

    /**
     * Buang satu berkas bukti hanya bila tidak ada catatan lain yang memakainya.
     *
     * @return bool true bila berkasnya benar benar dihapus
     */
    public function forgetWhenOrphaned(string $path, ?int $exceptLogId = null): bool
    {
        if ($path === '' || $this->references($path, $exceptLogId) > 0) {
            return false;
        }

        return $this->forget($path);
    }

    /**
     * Alamat halaman untuk membuka bukti foto sebuah catatan absen.
     */
    public function url(?SolatAttendanceLog $log): ?string
    {
        if ($log === null || $this->path($log) === null) {
            return null;
        }

        return route('attendance.proof', $log);
    }

    /**
     * Apakah berkas buktinya masih benar-benar ada di cakram.
     */
    public function exists(?string $path): bool
    {
        return is_string($path) && $path !== '' && Storage::disk(self::Disk)->exists($path);
    }

    /**
     * Bersihkan semua bukti foto milik seorang anggota.
     *
     * Dipakai sebelum catatan absennya ikut terhapus bersama anggotanya, supaya
     * tidak ada berkas yang tertinggal tanpa catatan.
     */
    public function forgetForUser(User $user): int
    {
        return $this->deleteFiles(
            SolatAttendanceLog::query()
                ->where('user_id', $user->getKey())
                ->whereNotNull('proof_photo_path')
                ->pluck('proof_photo_path'),
            // Catatan milik anggota ini sedang menuju ikut terhapus, jadi
            // acuannya tidak dihitung lagi.
            static fn (Builder $query) => $query->where('user_id', $user->getKey()),
        );
    }

    /**
     * Bersihkan semua bukti foto milik kumpulan sesi.
     *
     * Dipakai sebelum sesi dihapus, karena catatan kehadirannya ikut terhapus
     * bersama sesinya sehingga berkasnya tidak akan bisa ditemukan lagi.
     *
     * @param  array<int, int|string>  $sessionIds
     */
    public function forgetForSessions(array $sessionIds): int
    {
        if ($sessionIds === []) {
            return 0;
        }

        return $this->deleteFiles(
            SolatAttendanceLog::query()
                ->whereIn('solat_session_id', $sessionIds)
                ->whereNotNull('proof_photo_path')
                ->pluck('proof_photo_path'),
            // Catatan milik sesi sesi ini sedang menuju ikut terhapus.
            static fn (Builder $query) => $query->whereIn('solat_session_id', $sessionIds),
        );
    }

    /**
     * Buang satu berkas bukti.
     *
     * Dipakai saat catatan absen ditimpa dengan foto yang lebih baru, supaya
     * foto lama tidak tertinggal tanpa catatan.
     */
    public function forget(?string $path): bool
    {
        return is_string($path) && $path !== '' && Storage::disk(self::Disk)->delete($path);
    }

    /**
     * @param  iterable<int, mixed>  $paths
     * @param  (callable(Builder<SolatAttendanceLog>): void)|null  $disappearing  menandai catatan yang sedang menuju hilang
     */
    private function deleteFiles(iterable $paths, ?callable $disappearing = null): int
    {
        $daftar = [];

        foreach ($paths as $path) {
            if (is_string($path) && $path !== '') {
                $daftar[$path] = true;
            }
        }

        if ($daftar === []) {
            return 0;
        }

        // Berkas yang masih dipakai catatan LAIN tidak boleh dihapus. Catatan
        // yang sedang menuju hilang (misalnya karena anggotanya atau sesinya
        // dihapus) tidak dihitung lagi, sebab acuannya akan lenyap bersamaan.
        // Seluruh berkas diperiksa sekaligus supaya jumlah permintaan ke basis
        // data tetap satu.
        $semua = $this->referencedPaths(array_keys($daftar));
        $akanHilang = $disappearing === null ? [] : $this->referencedPaths(array_keys($daftar), $disappearing);

        $deleted = 0;

        foreach (array_keys($daftar) as $path) {
            if (($semua[$path] ?? 0) - ($akanHilang[$path] ?? 0) > 0) {
                continue;
            }

            if (Storage::disk(self::Disk)->delete($path)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Jumlah catatan absen yang menunjuk setiap berkas pada daftar.
     *
     * @param  array<int, string>  $paths
     * @param  (callable(Builder<SolatAttendanceLog>): void)|null  $only  menyaring catatan yang dihitung
     * @return array<string, int> lokasi berkas => jumlah catatan yang memakainya
     */
    private function referencedPaths(array $paths, ?callable $only = null): array
    {
        if ($paths === []) {
            return [];
        }

        $query = SolatAttendanceLog::query()->whereIn('proof_photo_path', $paths);

        if ($only !== null) {
            $only($query);
        }

        return $query
            ->selectRaw('proof_photo_path, COUNT(*) as jumlah')
            ->groupBy('proof_photo_path')
            ->pluck('jumlah', 'proof_photo_path')
            ->map(fn ($jumlah): int => (int) $jumlah)
            ->all();
    }

    /**
     * Buang bukti foto yang lebih tua dari umur simpan.
     *
     * Catatan absennya tidak dihapus, hanya lokasi fotonya yang dikosongkan.
     * Berkas tanpa catatan (misalnya catatan anggota yang sudah dihapus) ikut
     * dibersihkan per bulan penuh.
     *
     * @return array{files: int, logs: int}
     */
    public function purgeOlderThan(int $days): array
    {
        $cutoff = Carbon::now()->subDays(max(1, $days))->startOfDay();

        $files = 0;
        $logs = 0;
        $dikumpulkan = [];

        // Lokasi berkasnya dikosongkan lebih dulu, baru berkasnya diperiksa.
        // Satu berkas bisa dipakai bersama beberapa catatan absen, jadi baru
        // boleh dihapus setelah tidak ada satu pun catatan yang menunjuknya.
        SolatAttendanceLog::query()
            ->whereNotNull('proof_photo_path')
            ->where('scanned_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$dikumpulkan, &$logs): void {
                foreach ($rows as $row) {
                    $path = $row->proof_photo_path;

                    if (is_string($path) && $path !== '') {
                        $dikumpulkan[$path] = true;
                    }

                    $row->forceFill(['proof_photo_path' => null])->save();
                    $logs++;
                }
            });

        foreach (array_keys($dikumpulkan) as $path) {
            if ($this->references($path) > 0) {
                continue;
            }

            if (Storage::disk(self::Disk)->delete($path)) {
                $files++;
            }
        }

        foreach ($this->expiredMonthFolders($cutoff) as $folder) {
            $files += count(Storage::disk(self::Disk)->allFiles($folder));
            Storage::disk(self::Disk)->deleteDirectory($folder);
        }

        return ['files' => $files, 'logs' => $logs];
    }

    /**
     * Semua folder bulan di bawah folder bukti.
     *
     * @return array<int, string>
     */
    public function monthFolders(): array
    {
        $years = Storage::disk(self::Disk)->directories(self::Folder);
        $folders = [];

        foreach ($years as $year) {
            foreach (Storage::disk(self::Disk)->directories($year) as $month) {
                $folders[] = $month;
            }
        }

        sort($folders);

        return $folders;
    }

    /**
     * Folder bulan yang seluruh harinya sudah lewat batas umur simpan.
     *
     * @return array<int, string>
     */
    private function expiredMonthFolders(CarbonInterface $cutoff): array
    {
        $expired = [];

        foreach ($this->monthFolders() as $folder) {
            $month = Str::after($folder, self::Folder.'/');
            $parts = explode('/', $month);

            if (count($parts) !== 2 || ! ctype_digit($parts[0]) || ! ctype_digit($parts[1])) {
                continue;
            }

            $end = Carbon::create((int) $parts[0], (int) $parts[1], 1)->endOfMonth()->startOfDay();

            if ($end->lessThan(Carbon::parse($cutoff))) {
                $expired[] = $folder;
            }
        }

        return $expired;
    }
}
