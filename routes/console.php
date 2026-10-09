<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Berkas foto wajah sementara dan catatan antrean lama dibersihkan sendiri
// setiap hari supaya tidak ada potret yang menumpuk di server.
Schedule::command('face:purge-uploads')->daily();
