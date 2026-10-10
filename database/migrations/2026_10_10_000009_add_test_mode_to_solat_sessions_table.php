<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda sesi uji coba.
     *
     * Sesi uji coba dipakai admin untuk mencoba kamera absen tanpa membuka sesi
     * solat yang sungguhan. Barisnya tetap tinggal di tabel yang sama supaya
     * catatan kehadiran, foto bukti, dan daftar log memakai jalan yang sudah
     * ada, tetapi seluruh hitungan rekap dan mesin sesi otomatis menyaringnya
     * lewat kolom ini. Nama waktu solatnya sengaja "testing" agar tidak pernah
     * bertabrakan dengan waktu solat yang sebenarnya.
     */
    public function up(): void
    {
        Schema::table('solat_sessions', function (Blueprint $table) {
            $table->boolean('is_test')->default(false)->after('status');
            $table->index(['is_test', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('solat_sessions', function (Blueprint $table) {
            $table->dropIndex(['is_test', 'date']);
            $table->dropColumn('is_test');
        });
    }
};
