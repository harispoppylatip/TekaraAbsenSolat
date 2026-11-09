<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereIn('role', ['petugas', 'admin'])
            ->where('can_scan_face', false)
            ->update(['can_scan_face' => true]);
    }

    public function down(): void
    {
        //
    }
};
