<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_hanya_petugas_dan_admin_yang_bisa_mengunduh_rekap(): void
    {
        $murid = $this->makeUser(User::RoleMurid);
        $petugas = $this->makeUser(User::RolePetugas);
        $admin = $this->makeUser(User::RoleAdmin);

        $this->actingAs($murid)
            ->get(route('reports.export'))
            ->assertRedirect(route('murid.riwayat'));

        $this->actingAs($petugas)
            ->get(route('reports.export'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('reports.export'))
            ->assertOk();
    }

    public function test_unduh_rekap_solat_default_semicolon_dengan_bom_dan_sep_header(): void
    {
        $admin = $this->makeUser(User::RoleAdmin, ['name' => 'Admin Utama', 'identifier_number' => 'ADM-001']);
        $this->seedPrayerTimes();

        $response = $this->actingAs($admin)
            ->get(route('reports.export'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        // Memastikan UTF-8 BOM dan header sep=; ada di awal berkas untuk Microsoft Excel
        $this->assertStringStartsWith("\xEF\xBB\xBFsep=;", $content);
        // Memastikan baris data dipisahkan dengan titik koma (;)
        $this->assertStringContainsString(';Nama;Peran;Kelas;Hadir;Terlambat;', $content);
        $this->assertStringContainsString('ADM-001;"Admin Utama";Administrator;-;0;0;0;0', $content);
        $this->assertStringContainsString('Sesi;"Jumlah catatan";Hadir;Terlambat', $content);
    }

    public function test_unduh_rekap_solat_comma_dengan_bom_dan_sep_header(): void
    {
        $admin = $this->makeUser(User::RoleAdmin, ['name' => 'Admin Utama', 'identifier_number' => 'ADM-001']);
        $this->seedPrayerTimes();

        $response = $this->actingAs($admin)
            ->get(route('reports.export', ['delimiter' => 'comma']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        // Memastikan UTF-8 BOM dan header sep=, ada di awal berkas
        $this->assertStringStartsWith("\xEF\xBB\xBFsep=,", $content);
        // Memastikan baris data dipisahkan dengan koma (,)
        $this->assertStringContainsString(',Nama,Peran,Kelas,Hadir,Terlambat,', $content);
        $this->assertStringContainsString('ADM-001,"Admin Utama",Administrator,-,0,0,0,0', $content);
    }
}

