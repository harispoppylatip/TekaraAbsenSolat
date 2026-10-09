<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pemeriksaan ringan halaman masuk dan halaman utama.
 */
class EntryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_utama_mengarahkan_tamu_ke_halaman_masuk(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_halaman_masuk_menampilkan_nama_aplikasi(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(config('app.name'))
            ->assertSee('Masuk');
    }

    public function test_halaman_utama_mengarahkan_pengguna_yang_sudah_masuk(): void
    {
        $admin = $this->makeUser(User::RoleAdmin);

        $this->actingAs($admin)
            ->get('/')
            ->assertRedirect(route($admin->homeRoute()));
    }

    public function test_alamat_yang_tidak_ada_menjawab_tidak_ditemukan(): void
    {
        $this->get('/alamat-yang-tidak-ada')->assertNotFound();
    }
}
