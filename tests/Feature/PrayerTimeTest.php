<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrayerTimeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Formulir tambah waktu solat dibuka sesuai pilihan admin, bukan
     * memanjang sejak halaman pertama dibuka.
     */
    public function test_formulir_tambah_waktu_solat_tampil_tertutup(): void
    {
        $admin = $this->makeUser(User::RoleAdmin);
        $this->seedPrayerTimes();

        $html = $this->actingAs($admin)
            ->get(route('prayer-times.index'))
            ->assertOk()
            ->assertSee('Formulir waktu solat')
            ->getContent();

        $tag = $this->panelTag($html);

        $this->assertNotSame('', $tag, 'Panel formulir waktu solat tidak ditemukan.');
        $this->assertStringNotContainsString('open', $tag);
    }

    /**
     * Galat isian harus terbaca. Panel yang tertutup membuka dirinya sendiri
     * saat ada galat, jadi pesannya tidak bersembunyi di balik lipatan.
     */
    public function test_galat_isian_membuka_formulir(): void
    {
        $admin = $this->makeUser(User::RoleAdmin);
        $this->seedPrayerTimes();

        $html = $this->actingAs($admin)
            ->followingRedirects()
            ->from(route('prayer-times.index'))
            ->post(route('prayer-times.store'), [])
            ->assertOk()
            ->assertSee('Kode waktu solat wajib diisi.')
            ->assertSee('Nama waktu solat wajib diisi.')
            ->getContent();

        $this->assertStringContainsString('open', $this->panelTag($html));
    }

    /**
     * Isian yang sudah masuk ke dalam panel tetap tersimpan seperti semula.
     */
    public function test_tambah_waktu_solat_masih_tersimpan(): void
    {
        $admin = $this->makeUser(User::RoleAdmin);

        $this->actingAs($admin)
            ->post(route('prayer-times.store'), [
                'prayer_name' => 'dhuha',
                'label' => 'Solat Dhuha',
                'start_time' => '09:00',
                'end_time' => '09:30',
                'late_minutes' => 10,
                'sort_order' => 6,
                'is_active' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Waktu solat Solat Dhuha ditambahkan.');

        $this->assertDatabaseHas('prayer_times', [
            'prayer_name' => 'dhuha',
            'label' => 'Solat Dhuha',
            'late_minutes' => 10,
        ]);
    }

    /**
     * Mengambil tag pembuka <details> milik panel formulir supaya sifat tutup
     * atau bukanya bisa diperiksa tanpa terikat spasi atribut.
     */
    private function panelTag(string $html): string
    {
        preg_match('/<details[^>]*data-record="tool-tambah-waktu-solat"[^>]*>/', $html, $matches);

        return $matches[0] ?? '';
    }
}
