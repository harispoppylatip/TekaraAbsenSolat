<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sesi absen per waktu solat per tanggal.
     * Absen hanya bisa dicatat saat sesi berstatus sedang dibuka.
     */
    public function up(): void
    {
        Schema::create('solat_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('prayer_name');
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('status')->default('belum_dibuka');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['prayer_name', 'date']);
            $table->index(['date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solat_sessions');
    }
};
