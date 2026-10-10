<?php

namespace App\Console\Commands;

use App\Services\SolatProofService;
use Illuminate\Console\Command;

/**
 * Bersihkan foto bukti absen yang sudah melewati umur simpan.
 *
 * Catatan absennya tidak pernah dihapus oleh perintah ini, hanya lokasi
 * fotonya yang dikosongkan supaya riwayat tetap utuh tetapi cakramnya tidak
 * penuh. Umur simpannya diambil dari SOLAT_PROOF_RETENTION_DAYS.
 */
class PurgeSolatProofs extends Command
{
    protected $signature = 'solat:bersihkan-bukti {--days= : Umur simpan dalam hari, menimpa pengaturan}';

    protected $description = 'Buang foto bukti absen yang sudah melewati umur simpan';

    public function handle(SolatProofService $proofs): int
    {
        if (! config('solat.keep_proof_photo', true)) {
            $this->info('Bukti foto absen sedang dimatikan, tidak ada yang perlu dibersihkan.');

            return self::SUCCESS;
        }

        $days = $this->option('days');
        $days = $days !== null && $days !== ''
            ? (int) $days
            : (int) config('solat.proof_retention_days', 90);

        if ($days <= 0) {
            $this->info('Umur simpan bukti foto diisi 0, jadi semua bukti disimpan selamanya.');

            return self::SUCCESS;
        }

        $result = $proofs->purgeOlderThan($days);

        if ($result['files'] === 0 && $result['logs'] === 0) {
            $this->info('Tidak ada bukti foto yang lebih tua dari '.$days.' hari.');

            return self::SUCCESS;
        }

        $this->info('Dibuang '.$result['files'].' berkas bukti dari '.$result['logs'].' catatan absen yang lebih tua dari '.$days.' hari.');

        return self::SUCCESS;
    }
}
