<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catatan kehadiran anggota pada sebuah sesi solat.
     */
    public function up(): void
    {
        Schema::create('solat_attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('solat_session_id')->constrained()->cascadeOnDelete();
            $table->timestamp('scanned_at');
            $table->string('status')->default('hadir');
            $table->decimal('match_distance', 6, 4)->nullable();
            $table->string('device_id')->nullable();
            $table->timestamps();

            $table->unique(['solat_session_id', 'user_id']);
            $table->index(['user_id', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solat_attendance_logs');
    }
};
