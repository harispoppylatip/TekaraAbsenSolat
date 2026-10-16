<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MemberImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_hanya_admin_yang_boleh_membuka_halaman_impor_anggota(): void
    {
        $murid = $this->makeUser(User::RoleMurid);
        $petugas = $this->makeUser(User::RolePetugas);
        $admin = $this->makeUser(User::RoleAdmin);

        $this->actingAs($murid)
            ->get(route('members.import'))
            ->assertRedirect(route('murid.riwayat'));

        $this->actingAs($petugas)
            ->get(route('members.import'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($admin)
            ->get(route('members.import'))
            ->assertOk()
            ->assertSee('Impor Anggota')
            ->assertSee('Unduh Template Excel');
    }

    public function test_admin_bisa_mengunduh_template_csv(): void
    {
        $admin = $this->makeUser(User::RoleAdmin);

        // Default: Semicolon (bawaan Excel Indonesia)
        $response = $this->actingAs($admin)
            ->get(route('members.import.template'))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="template-impor-anggota-excel.csv"');

        $content = $response->streamedContent();
        $this->assertStringContainsString('nama;nisn;email;peran;kelas', $content);
        $this->assertStringContainsString('murid', $content);

        // Alternatif: Koma (standar CSV internasional)
        $responseComma = $this->actingAs($admin)
            ->get(route('members.import.template', ['delimiter' => 'comma']))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="template-impor-anggota.csv"');

        $contentComma = $responseComma->streamedContent();
        $this->assertStringContainsString('nama,nisn,email,peran,kelas', $contentComma);
    }

    public function test_admin_bisa_impor_csv_massal_dengan_email_opsional(): void
    {
        $admin = $this->makeUser(User::RoleAdmin);

        $csvContent = implode("\n", [
            'nama,nisn,email,peran,kelas',
            'Budi Santoso,26001,,murid,X TKJ 1',
            'Siti Rahma,26002,siti@sekolah.sch.id,murid,X TKJ 2',
            'Ustadz Hamzah,GUR-01,hamzah@sekolah.sch.id,guru,',
        ]);

        $file = UploadedFile::fake()->createWithContent('anggota.csv', $csvContent);

        $response = $this->actingAs($admin)
            ->post(route('members.import.store'), [
                'file' => $file,
                'update_existing' => '1',
            ]);

        $response->assertRedirect(route('members.index'));
        $response->assertSessionHas('status');

        // Budi tanpa email, role murid, kelas X TKJ 1, sandi bawaan = NISN
        $budi = User::where('identifier_number', '26001')->first();
        $this->assertNotNull($budi);
        $this->assertSame('Budi Santoso', $budi->name);
        $this->assertNull($budi->email);
        $this->assertSame(User::RoleMurid, $budi->role);
        $this->assertSame('X TKJ 1', $budi->class_name);
        $this->assertTrue(password_verify('26001', (string) $budi->password));
        $this->assertNull($budi->password_changed_at);

        // Budi bisa login pakai NISN dan sandi NISN
        $this->post(route('logout'));
        $loginRes = $this->post(route('login.store'), [
            'login' => '26001',
            'password' => '26001',
        ]);
        $loginRes->assertRedirect(route('murid.riwayat'));
        $this->assertAuthenticatedAs($budi);

        // Siti dengan email
        $siti = User::where('identifier_number', '26002')->first();
        $this->assertNotNull($siti);
        $this->assertSame('siti@sekolah.sch.id', $siti->email);
        $this->assertSame('X TKJ 2', $siti->class_name);

        // Guru Hamzah
        $hamzah = User::where('identifier_number', 'GUR-01')->first();
        $this->assertNotNull($hamzah);
        $this->assertSame(User::RolePetugas, $hamzah->role);
        $this->assertNull($hamzah->class_name);
    }

    public function test_impor_csv_memperbarui_anggota_yang_sudah_ada(): void
    {
        $admin = $this->makeUser(User::RoleAdmin);
        $murid = $this->makeUser(User::RoleMurid, [
            'identifier_number' => '26005',
            'name' => 'Nama Lama',
            'class_name' => 'X TKJ 1',
        ]);

        $csvContent = implode("\n", [
            'nama,nisn,email,peran,kelas',
            'Nama Baru,26005,baru@sekolah.sch.id,murid,XI TKJ 1',
        ]);

        $file = UploadedFile::fake()->createWithContent('update.csv', $csvContent);

        $this->actingAs($admin)
            ->post(route('members.import.store'), [
                'file' => $file,
                'update_existing' => '1',
            ])
            ->assertRedirect(route('members.index'));

        $murid->refresh();
        $this->assertSame('Nama Baru', $murid->name);
        $this->assertSame('baru@sekolah.sch.id', $murid->email);
        $this->assertSame('XI TKJ 1', $murid->class_name);
    }

    public function test_impor_csv_melewati_baris_bermasalah_dan_melaporkan_peringatan(): void
    {
        $admin = $this->makeUser(User::RoleAdmin);
        $existing = $this->makeUser(User::RoleMurid, ['email' => 'ada@sekolah.sch.id']);

        $csvContent = implode("\n", [
            'nama,nisn,email,peran,kelas',
            ',26010,,murid,X TKJ 1', // nama kosong
            'Nama Sah,26011,email-salah,murid,X TKJ 1', // format email salah
            'Nama Lain,26012,ada@sekolah.sch.id,murid,X TKJ 1', // email sudah dipakai
            'Siswa Sukses,26013,,murid,X TKJ 1', // sukses
        ]);

        $file = UploadedFile::fake()->createWithContent('bermasalah.csv', $csvContent);

        $this->actingAs($admin)
            ->post(route('members.import.store'), [
                'file' => $file,
            ])
            ->assertRedirect(route('members.index'))
            ->assertSessionHas('warning');

        $this->assertNull(User::where('identifier_number', '26010')->first());
        $this->assertNull(User::where('identifier_number', '26011')->first());
        $this->assertNull(User::where('identifier_number', '26012')->first());
        $this->assertNotNull(User::where('identifier_number', '26013')->first());
    }

    public function test_tambah_anggota_manual_dengan_email_kosong(): void
    {
        $admin = $this->makeUser(User::RoleAdmin);

        $this->actingAs($admin)
            ->post(route('members.store'), [
                'name' => 'Murid Tanpa Email',
                'identifier_number' => '27001',
                'email' => '',
                'role' => User::RoleMurid,
                'class_name' => 'X TKJ 1',
            ])
            ->assertRedirect();

        $user = User::where('identifier_number', '27001')->first();
        $this->assertNotNull($user);
        $this->assertSame('Murid Tanpa Email', $user->name);
        $this->assertNull($user->email);
    }

    public function test_pengguna_bisa_mengisi_atau_memperbarui_email_sendiri(): void
    {
        $murid = $this->makeUser(User::RoleMurid, ['email' => null]);

        $this->actingAs($murid)
            ->put(route('profile.email'), [
                'email' => 'pribadi@gmail.com',
            ])
            ->assertRedirect();

        $this->assertSame('pribadi@gmail.com', $murid->refresh()->email);

        // Bisa mengosongkan kembali jika diinginkan
        $this->actingAs($murid)
            ->put(route('profile.email'), [
                'email' => '',
            ])
            ->assertRedirect();

        $this->assertNull($murid->refresh()->email);
    }

    public function test_admin_bisa_impor_csv_titik_koma_dan_typo_murdi_seperti_excel_indonesia(): void
    {
        $admin = $this->makeUser(User::RoleAdmin);

        // Simulasi persis seperti input gambar user: nama, nisn, email, peran (murdi), kelas
        $csvContent = implode("\n", [
            'nama;nisn;email;peran;kelas',
            'hari;3323;contoh@gmail.com;murdi;X TKJ',
        ]);

        $file = UploadedFile::fake()->createWithContent('data-excel.csv', $csvContent);

        $response = $this->actingAs($admin)
            ->post(route('members.import.store'), [
                'file' => $file,
            ]);

        $response->assertRedirect(route('members.index'));
        $response->assertSessionHas('status');

        $user = User::where('identifier_number', '3323')->first();
        $this->assertNotNull($user);
        $this->assertSame('hari', $user->name);
        $this->assertSame('contoh@gmail.com', $user->email);
        $this->assertSame(User::RoleMurid, $user->role);
        $this->assertSame('X TKJ', $user->class_name);
        $this->assertTrue(password_verify('3323', (string) $user->password));
    }

    public function test_admin_bisa_impor_csv_dengan_directive_sep(): void
    {
        $admin = $this->makeUser(User::RoleAdmin);

        $csvContent = implode("\n", [
            'sep=,',
            'nama,nisn,email,peran,kelas',
            'Fahri,99001,,murid,XI TKJ 2',
        ]);

        $file = UploadedFile::fake()->createWithContent('sep-test.csv', $csvContent);

        $response = $this->actingAs($admin)
            ->post(route('members.import.store'), [
                'file' => $file,
            ]);

        $response->assertRedirect(route('members.index'));

        $user = User::where('identifier_number', '99001')->first();
        $this->assertNotNull($user);
        $this->assertSame('Fahri', $user->name);
        $this->assertSame('XI TKJ 2', $user->class_name);
    }
}
