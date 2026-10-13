<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Status pendaftaran wajah milik pemilik akun sendiri.
     *
     * Sebelum ini, "sudah punya wajah atau belum" hanya bisa ditebak dari jumlah
     * baris di tabel angka wajah. Cara itu tidak bisa membedakan anggota yang
     * belum pernah mulai dari anggota yang sudah mengirim foto tetapi fotonya
     * ditolak, dan justru dua keadaan itulah yang perlu dibedakan supaya
     * halaman pendaftaran tahu harus menampilkan apa kepada pemiliknya.
     *
     * Kolomnya diletakkan di users, bukan di tabel baru, karena keadaannya
     * memang satu per akun dan sudah ada tabel lain (face_photo_uploads) yang
     * menyimpan kemajuan tiap foto.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Salah satu nilai User::EnrollmentStatuses: not_started,
            // in_progress, processing, completed, rejected, needs_reenrollment.
            $table->string('enrollment_status', 24)->default('not_started')->after('can_scan_face');
            $table->timestamp('enrollment_started_at')->nullable()->after('enrollment_status');
            $table->timestamp('enrolled_at')->nullable()->after('enrollment_started_at');

            $table->index('enrollment_status');
        });

        // Anggota yang sudah punya angka wajah ditandai selesai supaya daftar
        // "belum mendaftar" di panel admin tidak memuat seluruh sekolah pada
        // hari pertama kolom ini dipasang.
        DB::table('users')
            ->whereIn('id', DB::table('face_embeddings')->select('user_id'))
            ->update([
                'enrollment_status' => 'completed',
                'enrolled_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['enrollment_status']);
            $table->dropColumn(['enrollment_status', 'enrollment_started_at', 'enrolled_at']);
        });
    }
};
