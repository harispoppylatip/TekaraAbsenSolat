<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan kolom identitas absen solat ke tabel pengguna bawaan.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('identifier_number')->nullable()->unique()->after('name');
            $table->string('role')->default('murid')->after('password');
            $table->string('class_name')->nullable()->after('role');
            $table->string('phone_number')->nullable()->after('class_name');
            $table->boolean('can_scan_face')->default(false)->after('phone_number');
            $table->timestamp('last_login_at')->nullable()->after('can_scan_face');
            $table->timestamp('password_changed_at')->nullable()->after('last_login_at');
            $table->index('role');
        });
    }

    /**
     * Mengembalikan tabel pengguna ke bentuk bawaan.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropUnique(['identifier_number']);
            $table->dropColumn([
                'identifier_number',
                'role',
                'class_name',
                'phone_number',
                'can_scan_face',
                'last_login_at',
                'password_changed_at',
            ]);
        });
    }
};
