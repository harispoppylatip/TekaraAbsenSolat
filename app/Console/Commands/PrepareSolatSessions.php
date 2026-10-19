<?php

namespace App\Console\Commands;

use App\Models\PrayerTime;
use App\Services\SolatSessionService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Siapkan sesi absen solat untuk hari ini dan hari berikutnya.
 *
 * Dijalankan penjadwal sesudah tengah malam supaya daftar sesi hari itu sudah
 * lengkap sejak dini hari, bukan baru muncul saat jendela waktunya dimulai.
 * Sesi yang dibuat di sini masih berkedudukan Belum dibuka: yang membuka dan
 * menutupnya tetap perintah solat:sesi-otomatis setiap menit.
 *
 * Perintah ini aman dijalankan berkali-kali. Sesi yang sudah ada tidak
 * diganggu, termasuk yang sudah dibuka atau ditutup petugas, sehingga catatan
 * kehadiran yang sudah masuk tidak pernah hilang.
 */
class PrepareSolatSessions extends Command
{
    protected $signature = 'solat:sesi-harian
        {--date= : Tanggal awal yang disiapkan, bawaan hari ini, contoh "2026-10-11"}
        {--days= : Berapa hari sesudah tanggal awal ikut disiapkan, bawaan dari pengaturan}';

    protected $description = 'Siapkan sesi absen solat harian supaya daftar sesi sudah lengkap sebelum jendelanya dimulai';

    public function handle(SolatSessionService $sessions): int
    {
        if (PrayerTime::query()->active()->count() === 0) {
            $this->warn('Belum ada waktu solat yang aktif. Isi dulu daftar waktu solat di halaman Waktu Solat.');

            return self::SUCCESS;
        }

        $start = filled($this->option('date')) ? Carbon::parse($this->option('date')) : Carbon::now();
        $days = filled($this->option('days'))
            ? max(0, (int) $this->option('days'))
            : max(0, (int) config('solat.prepare_days_ahead', 1));

        $created = 0;
        $skipped = 0;
        $offDays = 0;

        for ($offset = 0; $offset <= $days; $offset++) {
            $day = $start->copy()->startOfDay()->addDays($offset);

            if (! $sessions->isActiveDay($day)) {
                $offDays++;
                $this->line($day->translatedFormat('l, d F Y').': Hari libur absen, dilewati.');

                continue;
            }

            $result = $sessions->generateForDate($day);

            $created += $result['created'];
            $skipped += $result['skipped'];

            $this->line($day->translatedFormat('l, d F Y').': '.$result['created'].' sesi baru, '
                .$result['skipped'].' sudah ada.');
        }

        $this->newLine();
        $this->info('Sesi absen disiapkan untuk '.($days + 1).' hari: '.$created.' sesi baru, '
            .$skipped.' sesi sudah ada sebelumnya'
            .($offDays > 0 ? ', '.$offDays.' hari libur dilewati.' : '.'));

        if ($created === 0) {
            $this->line('Tidak ada sesi baru. Semua waktu solat aktif sudah punya sesi pada rentang itu.');
        }

        return self::SUCCESS;
    }
}
