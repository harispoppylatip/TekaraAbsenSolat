<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_masuk_bisa_dibuka(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Masuk', false);
    }

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_pengurus_masuk_dan_mendarat_di_ringkasan(): void
    {
        $this->seedPrayerTimes();
        $admin = $this->makeUser(User::RoleAdmin, ['password' => 'rahasia123']);

        $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'rahasia123'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->assertNotNull($admin->refresh()->last_login_at);
    }

    public function test_petugas_memasuki_ringkasan_dan_bukan_data_anggota(): void
    {
        $this->seedPrayerTimes();
        $petugas = $this->makeUser(User::RolePetugas);

        $this->actingAs($petugas)->get(route('dashboard'))->assertOk();

        // Halaman data anggota hanya untuk admin, jadi petugas dikembalikan ke
        // halaman awalnya lengkap dengan keterangan yang terbaca.
        $this->followingRedirects()
            ->actingAs($petugas)
            ->get(route('members.index'))
            ->assertOk()
            ->assertSee('Halaman itu hanya untuk Administrator');
    }

    public function test_murid_tidak_boleh_membuka_kamera_absen(): void
    {
        $murid = $this->makeUser(User::RoleMurid);

        $this->followingRedirects()
            ->actingAs($murid)
            ->get(route('attendance.wajah'))
            ->assertOk()
            ->assertSee('Kamera absen hanya bisa dipakai petugas yang ditugaskan admin');
    }

    public function test_petugas_yang_ditugaskan_bisa_membuka_kamera_absen(): void
    {
        $petugas = $this->makeUser(User::RolePetugas, ['can_scan_face' => true]);
        $this->fakeFaceService();

        $this->actingAs($petugas)
            ->get(route('attendance.wajah'))
            ->assertOk()
            ->assertSee('Kamera absen');
    }

    public function test_murid_dengan_sandi_bawaan_diarahkan_mengganti_sandi(): void
    {
        $murid = $this->makeUser(User::RoleMurid, [
            'password' => '260001',
            'password_changed_at' => null,
        ]);

        $this->post(route('login.store'), ['email' => $murid->email, 'password' => '260001'])
            ->assertRedirect(route('murid.riwayat'));

        $this->assertAuthenticatedAs($murid);

        // Halaman pertama yang dibuka setelah masuk tidak boleh terpakai dulu:
        // sandi bawaan harus diganti, dan alasannya harus terbaca di halaman itu.
        $this->followingRedirects()
            ->actingAs($murid)
            ->get(route('murid.riwayat'))
            ->assertOk()
            ->assertSee('Ganti dulu sebelum memakai halaman lain');
    }

    public function test_murid_masuk_dan_melihat_riwayat_absen_sendiri(): void
    {
        $murid = $this->makeUser(User::RoleMurid);

        $this->actingAs($murid)->get(route('dashboard'))->assertRedirect(route('murid.riwayat'));

        $this->actingAs($murid)
            ->get(route('murid.riwayat'))
            ->assertOk()
            ->assertSee($murid->name);
    }

    public function test_sandi_salah_menampilkan_pesan(): void
    {
        $admin = $this->makeUser(User::RoleAdmin);

        // Halaman masuk dibuka lebih dulu supaya pengalihan "kembali ke form"
        // punya tujuan yang nyata, seperti saat dipakai orang.
        $this->get(route('login'))->assertOk();

        $this->followingRedirects()
            ->post(route('login.store'), ['email' => $admin->email, 'password' => 'salah-sekali'])
            ->assertOk()
            ->assertSee('Email atau sandi tidak cocok');

        $this->assertGuest();
    }

    public function test_mengganti_sandi_menandai_sandi_sudah_diganti(): void
    {
        $murid = $this->makeUser(User::RoleMurid, [
            'password' => '260002',
            'password_changed_at' => null,
        ]);

        $this->actingAs($murid)
            ->put(route('password.update'), [
                'current_password' => '260002',
                'password' => 'SandiBaru2026!',
                'password_confirmation' => 'SandiBaru2026!',
            ])
            ->assertRedirect();

        $murid->refresh();

        $this->assertNotNull($murid->password_changed_at);
        $this->assertTrue(password_verify('SandiBaru2026!', (string) $murid->password));

        $this->actingAs($murid)->get(route('murid.riwayat'))->assertOk();
    }

    public function test_sandi_saat_ini_salah_ditolak(): void
    {
        $murid = $this->makeUser(User::RoleMurid);

        $this->actingAs($murid)->get(route('password.edit'))->assertOk();

        $this->followingRedirects()
            ->actingAs($murid)
            ->put(route('password.update'), [
                'current_password' => 'bukan-sandi',
                'password' => 'SandiBaru2026!',
                'password_confirmation' => 'SandiBaru2026!',
            ])
            ->assertOk()
            ->assertSee('Sandi saat ini tidak cocok');

        $this->assertTrue(Hash::check('rahasia123', (string) $murid->refresh()->password));
    }

    public function test_pengguna_bisa_keluar(): void
    {
        $admin = $this->makeUser(User::RoleAdmin);

        $this->actingAs($admin)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
