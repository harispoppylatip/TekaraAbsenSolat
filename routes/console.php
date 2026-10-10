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

// Sesi absen hari itu disiapkan sendiri sesudah tengah malam supaya daftar
// sesi sudah lengkap sejak dini hari, bukan baru muncul saat jendelanya
// dimulai. Sesi besoknya sekalian dibuat supaya pergantian hari tidak pernah
// kosong meski penjadwal sempat mati saat tengah malam. Jamnya bisa diubah
// lewat SOLAT_SESSION_CREATE_TIME.
Schedule::command('solat:sesi-harian')->dailyAt(config('solat.create_time'));

// Sesi absen dibuka dan ditutup sendiri mengikuti jam waktu solat. Diperiksa
// setiap menit supaya pergeseran menit tidak pernah terlewat, dan penjadwal
// ini wajib hidup supaya absen tidak bergantung pada siapa yang ingat
// menekan tombol Buka.
Schedule::command('solat:sesi-otomatis')->everyMinute();
