<?php

namespace Tests\Feature;

use App\Models\PrayerTime;
use App\Models\SolatSession;
use App\Models\User;
use App\Services\SolatSessionService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Sesi absen hari itu disiapkan sendiri oleh penjadwal sesudah tengah malam,
 * jadi petugas tidak perlu menekan tombol apa pun untuk memilikinya.
 */
class SolatDailySessionTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SolatSessionService
    {
        return app(SolatSessionService::class);
    }

    private function sessionsFor(Carbon $day): Collection
    {
        return SolatSession::query()->forDate($day)->get();
    }

    private function sessionFor(string $prayer = 'zuhur'): ?SolatSession
    {
        return SolatSession::query()
            ->where('prayer_name', $prayer)
            ->forDate(Carbon::today())
            ->first();
    }

    private function statuses(Carbon $day): array
    {
        return $this->sessionsFor($day)->pluck('status')->unique()->values()->all();
    }

    public function test_sesi_hari_ini_sudah_lengkap_sejak_dini_hari(): void
    {
        $this->seedPrayerTimes();
        $this->travelTo(Carbon::parse('today 00:30'));

        $this->artisan('solat:sesi-harian')
            ->expectsOutputToContain('sesi baru')
            ->assertSuccessful();

        $hari = $this->sessionsFor(Carbon::today());

        $this->assertCount(5, $hari);
        $this->assertSame([SolatSession::StatusBelumDibuka], $this->statuses(Carbon::today()));
        $this->assertSame(0, $hari->whereNotNull('opened_by')->count());
        $this->assertSame(0, $hari->whereNotNull('opened_at')->count());
        $this->assertNull($this->service()->activeSession());
    }

    public function test_sesi_besok_ikut_disiapkan_supaya_pergantian_hari_tidak_kosong(): void
    {
        $this->seedPrayerTimes();
        $this->travelTo(Carbon::parse('today 23:30'));

        $this->artisan('solat:sesi-harian')->assertSuccessful();

        $this->assertCount(5, $this->sessionsFor(Carbon::today()));
        $this->assertCount(5, $this->sessionsFor(Carbon::tomorrow()));
        $this->assertSame(10, SolatSession::query()->count());
    }

    public function test_menjalankan_perintah_dua_kali_tidak_menggandakan_sesi(): void
    {
        $this->seedPrayerTimes();
        $this->travelTo(Carbon::parse('today 01:00'));

        $this->artisan('solat:sesi-harian')->assertSuccessful();

        $this->artisan('solat:sesi-harian')
            ->expectsOutputToContain('Tidak ada sesi baru')
            ->assertSuccessful();

        $this->assertCount(5, $this->sessionsFor(Carbon::today()));
        $this->assertCount(5, $this->sessionsFor(Carbon::tomorrow()));
    }

    public function test_sesi_yang_disentuh_petugas_tidak_diganggu_perintah_harian(): void
    {
        $this->seedPrayerTimes();
        $this->makeSession('zuhur');

        $admin = $this->makeUser(User::RoleAdmin);

        $this->travelTo(Carbon::parse('today 12:05'));
        $this->service()->open($this->sessionFor(), $admin);
        $this->service()->close($this->sessionFor(), $admin);

        $this->travelTo(Carbon::parse('today 23:30'));
        $this->artisan('solat:sesi-harian')->assertSuccessful();

        $session = $this->sessionFor();

        $this->assertSame(SolatSession::StatusSudahDitutup, $session->status);
        $this->assertSame($admin->id, $session->opened_by);
        $this->assertSame($admin->id, $session->closed_by);
        $this->assertNotNull($session->closed_at);
        $this->assertCount(5, $this->sessionsFor(Carbon::today()));
    }

    public function test_waktu_solat_yang_dinonaktifkan_tidak_ikut_disiapkan(): void
    {
        $this->seedPrayerTimes();

        PrayerTime::query()->where('prayer_name', 'ashar')->update(['is_active' => false]);

        $this->travelTo(Carbon::parse('today 00:30'));
        $this->artisan('solat:sesi-harian')->assertSuccessful();

        $this->assertCount(4, $this->sessionsFor(Carbon::today()));
        $this->assertNull($this->sessionFor('ashar'));
    }

    public function test_perintah_harian_bisa_diarahkan_ke_tanggal_lain(): void
    {
        $this->seedPrayerTimes();
        $this->travelTo(Carbon::parse('today 08:00'));

        $this->artisan('solat:sesi-harian', [
            '--date' => Carbon::today()->addDays(3)->toDateString(),
            '--days' => 0,
        ])->assertSuccessful();

        $this->assertCount(5, $this->sessionsFor(Carbon::today()->addDays(3)));
        $this->assertSame(0, SolatSession::query()->forDate(Carbon::today())->count());
    }

    public function test_pengaturan_bisa_mematikan_penyiapan_hari_besok(): void
    {
        $this->seedPrayerTimes();
        config(['solat.prepare_days_ahead' => 0]);

        $this->travelTo(Carbon::parse('today 02:00'));
        $this->artisan('solat:sesi-harian')->assertSuccessful();

        $this->assertCount(5, $this->sessionsFor(Carbon::today()));
        $this->assertSame(0, SolatSession::query()->forDate(Carbon::tomorrow())->count());
    }

    public function test_perintah_harian_menahan_diri_saat_belum_ada_waktu_solat_aktif(): void
    {
        $this->artisan('solat:sesi-harian')
            ->expectsOutputToContain('Belum ada waktu solat yang aktif')
            ->assertSuccessful();

        $this->assertSame(0, SolatSession::query()->count());
    }

    public function test_sesi_yang_disiapkan_dini_hari_dibuka_mesin_tanpa_dibuat_ulang(): void
    {
        $this->seedPrayerTimes();
        $this->travelTo(Carbon::parse('today 00:30'));

        $this->artisan('solat:sesi-harian')->assertSuccessful();

        $sebelum = $this->sessionFor();

        $this->assertNotNull($sebelum);

        $this->travelTo(Carbon::parse('today 11:50'));
        $actions = $this->service()->runAutomatically();

        $this->assertSame('opened', $actions[0]['action']);
        $this->assertTrue($this->sessionFor()->is($sebelum));
        $this->assertSame(5, $this->sessionsFor(Carbon::today())->count());
    }

    public function test_batas_buka_petugas_mengikuti_pengaturan(): void
    {
        $this->seedPrayerTimes();
        $this->makeSession('zuhur');

        config(['solat.early_open_minutes' => 30]);

        $petugas = $this->makeUser(User::RolePetugas);
        $session = $this->sessionFor();

        // Dengan pengaturan 30 menit, pukul 11:29 masih terlalu awal.
        $this->travelTo(Carbon::parse('today 11:29'));
        $this->assertFalse($this->service()->canOpen($session, $petugas));

        $this->travelTo(Carbon::parse('today 11:30'));
        $this->assertTrue($this->service()->canOpen($session, $petugas));
    }

    public function test_mesin_sesi_ikut_memakai_pengaturan_yang_sama(): void
    {
        $this->seedPrayerTimes();
        config(['solat.early_open_minutes' => 30]);

        // Zuhur mulai 12:00, jadi dengan pengaturan 30 menit sesi sudah dibuka
        // pukul 11:31, padahal bawaan 10 menit belum bergerak.
        $this->travelTo(Carbon::parse('today 11:31'));
        $actions = $this->service()->runAutomatically();

        $this->assertSame('opened', $actions[0]['action']);
        $this->assertSame(SolatSession::StatusSedangDibuka, $this->sessionFor()->status);
    }

    public function test_kedua_perintah_sesi_terdaftar_pada_penjadwal(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('solat:sesi-harian')
            ->expectsOutputToContain('solat:sesi-otomatis')
            ->assertSuccessful();
    }
}
