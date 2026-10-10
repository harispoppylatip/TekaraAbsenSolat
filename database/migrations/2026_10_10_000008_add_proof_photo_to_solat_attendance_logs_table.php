<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foto bukti absen.
     *
     * Yang disimpan hanya lokasi berkasnya, sedangkan berkasnya sendiri berada
     * di cakram privat aplikasi. Tidak ada kolom ini bila bukti foto dimatikan
     * lewat SOLAT_KEEP_PROOF_PHOTO.
     */
    public function up(): void
    {
        Schema::table('solat_attendance_logs', function (Blueprint $table) {
            $table->string('proof_photo_path')->nullable()->after('device_id');
            $table->index('proof_photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('solat_attendance_logs', function (Blueprint $table) {
            $table->dropIndex(['proof_photo_path']);
            $table->dropColumn('proof_photo_path');
        });
    }
};
