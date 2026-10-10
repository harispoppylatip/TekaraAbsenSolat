<?php

namespace App\Services;

use App\Models\SolatAttendanceLog;
use App\Models\SolatSession;
use App\Models\User;
use Carbon\CarbonInterface;
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
        $paths = SolatAttendanceLog::query()
            ->where('user_id', $user->getKey())
            ->whereNotNull('proof_photo_path')
            ->pluck('proof_photo_path');

        $deleted = 0;

        foreach ($paths as $path) {
            if (is_string($path) && Storage::disk(self::Disk)->delete($path)) {
                $deleted++;
            }
        }

        return $deleted;
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

        SolatAttendanceLog::query()
            ->whereNotNull('proof_photo_path')
            ->where('scanned_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$files, &$logs): void {
                foreach ($rows as $row) {
                    $path = $row->proof_photo_path;

                    if (is_string($path) && Storage::disk(self::Disk)->delete($path)) {
                        $files++;
                    }

                    $row->forceFill(['proof_photo_path' => null])->save();
                    $logs++;
                }
            });

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
