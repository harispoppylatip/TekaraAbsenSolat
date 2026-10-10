<?php

namespace App\Console\Commands;

use App\Services\SolatSessionService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Buka dan tutup sesi absen solat mengikuti jam waktu solat.
 *
 * Dijalankan penjadwal setiap menit. Sesi dibuka sepuluh menit sebelum jam
 * mulai, angka yang sama dengan batas buka manual petugas, lalu ditutup begitu
 * jendela absennya berakhir. Tombol buka dan tutup di halaman Sesi Absen tetap
 * ada sebagai jalan keluar, misalnya saat jadwal sekolah berubah mendadak.
 */
class RunSolatSessionSchedule extends Command
{
    protected $signature = 'solat:sesi-otomatis {--at= : Periksa seolah-olah sekarang pukul ini, contoh "2026-10-10 15:05"}';

    protected $description = 'Buka dan tutup sesi absen solat otomatis mengikuti jam waktu solat';

    public function handle(SolatSessionService $sessions): int
    {
        $at = $this->option('at');
        $stamp = filled($at) ? Carbon::parse($at) : Carbon::now();

        $actions = $sessions->runAutomatically($stamp);

        if ($actions === []) {
            $this->info('Tidak ada sesi yang perlu dibuka atau ditutup pada '
                .$stamp->translatedFormat('d F Y, H:i').'.');

            return self::SUCCESS;
        }

        foreach ($actions as $action) {
            $this->line($action['message']);
        }

        $this->newLine();
        $this->info($this->summary($actions, $stamp));

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array{action: string, message: string}>  $actions
     */
    private function summary(array $actions, Carbon $stamp): string
    {
        $counts = ['opened' => 0, 'closed' => 0, 'waiting' => 0];

        foreach ($actions as $action) {
            $counts[$action['action']] = ($counts[$action['action']] ?? 0) + 1;
        }

        return 'Ringkasan '.$stamp->translatedFormat('d F Y, H:i').': '
            .$counts['opened'].' sesi dibuka, '.$counts['closed'].' sesi ditutup, '
            .$counts['waiting'].' sesi menunggu giliran.';
    }
}
