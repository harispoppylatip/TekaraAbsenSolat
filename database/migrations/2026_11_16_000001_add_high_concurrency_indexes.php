<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambahkan indeks komposit pada jalur panas (hot-path) yang dipanggil
     * setiap detik oleh Kiosk, CCTV Auto-Scan, Kamera Petugas, Ringkasan, dan Rekap.
     */
    public function up(): void
    {
        Schema::table('solat_attendance_logs', function (Blueprint $table): void {
            $table->index('scanned_at', 'idx_solat_logs_scanned_at');
            $table->index(['solat_session_id', 'status'], 'idx_solat_logs_session_status');
        });

        Schema::table('solat_sessions', function (Blueprint $table): void {
            $table->index(['status', 'is_test', 'date'], 'idx_solat_sessions_status_test_date');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->index(['role', 'enrollment_status'], 'idx_users_role_enrollment');
        });
    }

    public function down(): void
    {
        Schema::table('solat_attendance_logs', function (Blueprint $table): void {
            $table->dropIndex('idx_solat_logs_scanned_at');
            $table->dropIndex('idx_solat_logs_session_status');
        });

        Schema::table('solat_sessions', function (Blueprint $table): void {
            $table->dropIndex('idx_solat_sessions_status_test_date');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('idx_users_role_enrollment');
        });
    }
};
