<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan kolom jurusan (major) pada tabel pengguna.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'major')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('major', 80)->nullable()->after('class_name');
                $table->index('major');
            });
        }

        // Sinkronisasi awal: bila class_name lama memuat kode jurusan umum (mis. "XII TKJ 1"),
        // isi kolom major secara otomatis bila major masih kosong.
        $knownMajors = ['TKJ', 'TKRO', 'TKR', 'TBSM', 'TSM', 'AKL', 'AK', 'MPLB', 'OTKP', 'AP', 'DKV', 'MM', 'RPL', 'PPLG', 'TITL', 'TAV', 'LAS', 'TP'];

        $users = DB::table('users')
            ->whereNull('major')
            ->whereNotNull('class_name')
            ->where('class_name', '!=', '')
            ->get(['id', 'class_name']);

        foreach ($users as $user) {
            $upperClass = strtoupper((string) $user->class_name);
            foreach ($knownMajors as $code) {
                if (preg_match('/\b'.preg_quote($code, '/').'\b/', $upperClass)) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['major' => $code]);
                    break;
                }
            }
        }
    }

    /**
     * Menghapus kolom jurusan (major) dari tabel pengguna.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'major')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['major']);
                $table->dropColumn('major');
            });
        }
    }
};
