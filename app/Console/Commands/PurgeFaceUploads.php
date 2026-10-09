<?php

namespace App\Console\Commands;

use App\Services\FacePhotoQueueService;
use Illuminate\Console\Command;

/**
 * Bersihkan berkas foto sementara dan catatan antrean wajah yang sudah lama.
 *
 * Berkas foto biasanya sudah dihapus sendiri begitu angkanya tersimpan. Yang
 * tertinggal hanya dua hal: foto gagal karena layanan wajah mati, dan catatan
 * kemajuan lama. Perintah ini menyapunya supaya tidak menumpuk di server.
 */
class PurgeFaceUploads extends Command
{
    protected $signature = 'face:purge-uploads {--days=7 : Umur paling sedikit sebelum catatan dibuang}';

    protected $description = 'Buang berkas foto wajah sementara dan catatan antrean yang sudah lama';

    public function handle(FacePhotoQueueService $queue): int
    {
        $result = $queue->purge((int) $this->option('days'));

        if ($result['rows'] === 0) {
            $this->info('Tidak ada catatan antrean wajah yang perlu dibuang.');

            return self::SUCCESS;
        }

        $this->info('Dibuang '.$result['rows'].' catatan antrean dan '.$result['files'].' berkas foto sementara.');

        return self::SUCCESS;
    }
}
