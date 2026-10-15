<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambahkan kolom photo_path untuk menyimpan lokasi berkas foto wajah asli
     * di penyimpanan privat.
     */
    public function up(): void
    {
        Schema::table('face_embeddings', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('face_embeddings', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
