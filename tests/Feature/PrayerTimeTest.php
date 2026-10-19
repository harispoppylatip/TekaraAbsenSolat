<?php

namespace Tests\Feature;

use App\Models\SolatSession;
use App\Models\User;
use App\Services\SolatSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
     * Admin mengatur hari absen sekolah (contoh: Senin-Jumat aktif, Sabtu-Minggu libur)
     * di halaman /waktu-solat, dan penjadwal melewati hari libur secara otomatis.
     */
    public function test_admin_mengatur_hari_absen_sekolah_dan_hari_libur_dilewati_otomatis(): void
    {
        $admin = $this->makeUser(User::RoleAdmin);
        $petugas = $this->makeUser(User::RolePetugas);
        $this->seedPrayerTimes([1, 2, 3, 4, 5, 6, 7]);

        // Admin menyimpan pengaturan Senin-Jumat (1..5), Sabtu & Minggu (6, 7) libur
        $this->actingAs($admin)
            ->put(route('prayer-times.days.update'), [
                'days' => [1, 2, 3, 4, 5],
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Hari absen sekolah diperbarui (Aktif: Senin, Selasa, Rabu, Kamis, Jumat · Libur: Sabtu, Minggu).');

        $service = app(SolatSessionService::class);
        $this->assertSame([1, 2, 3, 4, 5], $service->activeDays());
        $this->assertTrue($service->isActiveDay('2026-10-23')); // Jumat
        $this->assertFalse($service->isActiveDay('2026-10-24')); // Sabtu
        $this->assertFalse($service->isActiveDay('2026-10-25')); // Minggu

        // Penjadwal harian (Jumat s/d Minggu): Jumat dibuat, Sabtu & Minggu dilewati
        $this->artisan('solat:sesi-harian', [
            '--date' => '2026-10-23',
            '--days' => 2,
        ])
            ->expectsOutputToContain('Hari libur absen, dilewati')
            ->assertSuccessful();

        $this->assertSame(5, SolatSession::query()->forDate('2026-10-23')->count());
        $this->assertSame(0, SolatSession::query()->forDate('2026-10-24')->count());
        $this->assertSame(0, SolatSession::query()->forDate('2026-10-25')->count());

        // Mesin sesi otomatis tidak membuka sesi pada hari Sabtu (libur)
        $actionsSabtu = $service->runAutomatically(Carbon::parse('2026-10-24 11:55'));
        $this->assertSame([], $actionsSabtu);
        $this->assertNull($service->activeSession());

        // Petugas ditolak membuat sesi manual pada hari Sabtu (libur)
        $this->actingAs($petugas)
            ->post(route('sessions.generate'), ['date' => '2026-10-24'])
            ->assertRedirect(route('sessions.index', ['date' => '2026-10-24']))
            ->assertSessionHas('error');

        $this->assertSame(0, SolatSession::query()->forDate('2026-10-24')->count());

        // Admin tetap bisa membuat sesi manual di hari libur jika ada kegiatan khusus sekolah
        $this->actingAs($admin)
            ->post(route('sessions.generate'), ['date' => '2026-10-24'])
            ->assertRedirect(route('sessions.index', ['date' => '2026-10-24']))
            ->assertSessionHas('status');

        $this->assertSame(5, SolatSession::query()->forDate('2026-10-24')->count());
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
