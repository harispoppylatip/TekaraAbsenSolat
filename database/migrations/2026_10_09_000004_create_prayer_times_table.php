<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengaturan waktu tiap solat yang menjadi dasar pembuatan sesi absen.
     */
    public function up(): void
    {
        Schema::create('prayer_times', function (Blueprint $table) {
            $table->id();
            $table->string('prayer_name')->unique();
            $table->string('label');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('late_minutes')->default(10);
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prayer_times');
    }
};
