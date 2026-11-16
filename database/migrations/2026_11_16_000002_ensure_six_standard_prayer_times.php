<?php

use App\Models\PrayerTime;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pastikan 6 waktu solat standar (Subuh, Duha, Zuhur, Ashar, Maghrib, Isya)
     * tersedia di tabel prayer_times bila tabel sudah berisi jadwal sebelumnya.
     */
    public function up(): void
    {
        if (! Schema::hasTable('prayer_times')) {
            return;
        }

        if (PrayerTime::query()->count() > 0) {
            PrayerTime::ensureStandardPrayers();
        }
    }

    public function down(): void
    {
        // Tidak menghapus data waktu solat standar
    }
};
