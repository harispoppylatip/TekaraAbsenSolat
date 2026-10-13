<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda foto yang diunggah pemilik akunnya sendiri.
     *
     * Pemeriksaan mutu foto (gelap, buram, arah hadap) dan pemeriksaan foto
     * kembar hanya dijalankan pada foto yang diunggah sendiri oleh pemilik akun,
     * dari halaman pendaftaran wajah. Alasannya dua: pendaftaran mandiri
     * dikerjakan orang yang belum terlatih sehingga perlu penjagaan ekstra,
     * sedangkan pendaftaran yang dikerjakan operator di panel harus tetap
     * berperilaku persis seperti sebelumnya.
     *
     * Penandanya disimpan di baris antrean, bukan sekadar disimpulkan dari
     * `uploaded_by` yang kosong, supaya asalnya terbaca terus walau pekerja
     * antreannya dijalankan ulang dan supaya alur lain yang kebetulan tidak
     * mengisi pengunggah tidak diam-diam ikut diperiksa ketat.
     */
    public function up(): void
    {
        Schema::table('face_photo_uploads', function (Blueprint $table) {
            $table->boolean('self_enrolled')->default(false)->after('label');
            $table->index(['user_id', 'self_enrolled']);
        });
    }

    public function down(): void
    {
        Schema::table('face_photo_uploads', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'self_enrolled']);
            $table->dropColumn('self_enrolled');
        });
    }
};
