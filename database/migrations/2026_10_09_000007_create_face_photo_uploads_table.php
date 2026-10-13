<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menampung foto wajah yang sedang menunggu diubah menjadi angka wajah.
 *
 * Halaman Wajah tidak lagi menahan admin menunggu mesin wajah. Fotonya
 * dititipkan di server, pekerjaan pengubahan angka masuk antrean, dan baris di
 * tabel ini yang mencatat kemajuannya. Berkas foto dihapus begitu selesai,
 * sehingga yang tersimpan selamanya tetap hanya 512 angka wajah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('face_photo_uploads', function (Blueprint $table): void {
            $table->id();
            $table->string('batch_id', 36)->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('label', 50)->nullable();
            $table->boolean('replace')->default(false);
            $table->string('status', 20)->default('menunggu')->index();
            $table->string('result_status', 32)->nullable();
            $table->text('message')->nullable();
            $table->foreignId('face_embedding_id')->nullable()->constrained('face_embeddings')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_photo_uploads');
    }
};
