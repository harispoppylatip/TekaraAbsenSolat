<?php

namespace Tests\Feature;

use App\Models\PrayerTime;
use App\Models\SolatSession;
use App\Models\User;
use App\Services\SolatSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Sesi absen dibuka dan ditutup sendiri oleh penjadwal, tanpa menyingkirkan
 * keputusan yang sudah diambil petugas atau admin.
 */
class SolatAutoSessionTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SolatSessionService
    {
        return app(SolatSessionService::class);
    }

    /**
     * @return array<int, array{action: string, message: string}>
     */
    private function runAt(string $time): array
    {
        $this->travelTo(Carbon::parse('today '.$time));

        return $this->service()->runAutomatically();
    }

    private function sessionFor(string $prayer = 'zuhur'): ?SolatSession
    {
        return SolatSession::query()
            ->where('prayer_name', $prayer)
            ->forDate(Carbon::today())
            ->first();
    }

    public function test_sesi_dibuka_otomatis_sepuluh_menit_sebelum_mulai(): void
    {
        $this->seedPrayerTimes();

        // Zuhur mulai 12:00, jadi mesin baru bergerak pukul 11:50.
        $this->assertSame([], $this->runAt('11:49'));
        $this->assertNull($this->sessionFor());

        $actions = $this->runAt('11:50');

        $session = $this->sessionFor();

        $this->assertNotNull($session);
        $this->assertSame(SolatSession::StatusSedangDibuka, $session->status);
        $this->assertNull($session->opened_by);
        $this->assertNotNull($session->opened_at);
        $this->assertTrue($this->service()->activeSession()?->is($session));
        $this->assertSame('opened', $actions[0]['action']);
        $this->assertStringContainsString('dibuka otomatis', $actions[0]['message']);
    }

    public function test_sesi_ditutup_otomatis_di_akhir_jendela(): void
    {
        $this->seedPrayerTimes();

        $this->runAt('11:50');

        // Zuhur berakhir 12:45, jadi pukul 12:45 masih terbuka.
        $this->assertSame([], $this->runAt('12:45'));
        $this->assertSame(SolatSession::StatusSedangDibuka, $this->sessionFor()->status);

        $actions = $this->runAt('12:46');
        $session = $this->sessionFor();

        $this->assertSame(SolatSession::StatusSudahDitutup, $session->status);
        $this->assertNull($session->closed_by);
        $this->assertNull($this->service()->activeSession());
        $this->assertStringContainsString('ditutup otomatis', $actions[0]['message']);
    }

    public function test_sesi_yang_ditutup_manusia_tidak_dibuka_lagi(): void
    {
        $this->seedPrayerTimes();
        $this->makeSession('zuhur');

        $admin = $this->makeUser(User::RoleAdmin);

        $this->travelTo(Carbon::parse('today 12:05'));
        $this->service()->open($this->sessionFor(), $admin);
        $this->service()->close($this->sessionFor(), $admin);

        // Mesin lewat lagi selagi jendelanya masih berjalan.
        $this->assertSame([], $this->runAt('12:20'));

        $session = $this->sessionFor();

        $this->assertSame(SolatSession::StatusSudahDitutup, $session->status);
        $this->assertSame($admin->id, $session->opened_by);
        $this->assertSame($admin->id, $session->closed_by);
    }

    public function test_sesi_yang_dibuka_petugas_ditutup_mesin_di_akhir_jendela(): void
    {
        $this->seedPrayerTimes();
        $this->makeSession('zuhur');

        $petugas = $this->makeUser(User::RolePetugas);

        $this->travelTo(Carbon::parse('today 12:05'));
        $this->service()->open($this->sessionFor(), $petugas);

        $this->runAt('12:46');

        $session = $this->sessionFor();

        $this->assertSame(SolatSession::StatusSudahDitutup, $session->status);
        $this->assertNull($session->closed_by);
        $this->assertSame($petugas->id, $session->opened_by);
    }

    public function test_sesi_yang_sengaja_dibuka_admin_setelah_jendela_lewat_dibiarkan(): void
    {
        $this->seedPrayerTimes();
        $this->makeSession('zuhur');

        $admin = $this->makeUser(User::RoleAdmin);

        // Admin membuka Zuhur pukul 13:00, jauh sesudah jendelanya berakhir.
        $this->travelTo(Carbon::parse('today 13:00'));
        $this->assertTrue($this->service()->open($this->sessionFor(), $admin)['ok']);

        $this->assertSame([], $this->runAt('13:05'));

        $session = $this->sessionFor();

        $this->assertSame(SolatSession::StatusSedangDibuka, $session->status);
        $this->assertNull($session->closed_at);
    }

    public function test_sesi_menunggu_giliran_ditandai_terjadwal(): void
    {
        $this->seedPrayerTimes();
        $this->makeSession('zuhur');

        $admin = $this->makeUser(User::RoleAdmin);

        // Sesi Zuhur dibiarkan terbuka sampai jendela Ashar masuk.
        $this->travelTo(Carbon::parse('today 15:05'));
        $this->assertTrue($this->service()->open($this->sessionFor(), $admin)['ok']);

        $actions = $this->runAt('15:06');

        $this->assertSame(['waiting'], array_column($actions, 'action'));
        $this->assertStringContainsString('menunggu', $actions[0]['message']);
        $this->assertStringContainsString('Zuhur', $actions[0]['message']);

        $ashar = $this->sessionFor('ashar');

        $this->assertNotNull($ashar);
        $this->assertSame(SolatSession::StatusTerjadwal, $ashar->status);
        $this->assertSame('Terjadwal', $ashar->statusLabel());
        $this->assertSame(SolatSession::StatusSedangDibuka, $this->sessionFor()->status);
    }

    public function test_sesi_yang_menunggu_terbuka_begitu_sesi_lain_ditutup(): void
    {
        $this->seedPrayerTimes();
        $this->makeSession('zuhur');

        $admin = $this->makeUser(User::RoleAdmin);

        $this->travelTo(Carbon::parse('today 15:05'));
        $this->service()->open($this->sessionFor(), $admin);
        $this->runAt('15:06');

        $this->service()->close($this->sessionFor(), $admin);

        $actions = $this->runAt('15:10');
        $ashar = $this->sessionFor('ashar');

        $this->assertSame('opened', $actions[0]['action']);
        $this->assertSame(SolatSession::StatusSedangDibuka, $ashar->status);
        $this->assertNull($ashar->opened_by);
    }

    public function test_sesi_yang_masih_terbuka_ditutup_sebelum_jendela_berikutnya_dibuka(): void
    {
        $this->seedPrayerTimes();

        $this->runAt('11:50');
        $this->runAt('15:06');

        $this->assertSame(SolatSession::StatusSudahDitutup, $this->sessionFor()->status);
        $this->assertSame(SolatSession::StatusSedangDibuka, $this->sessionFor('ashar')->status);
        $this->assertTrue($this->service()->activeSession()?->is($this->sessionFor('ashar')));
    }

    public function test_sesi_tertinggal_dari_hari_sebelumnya_ditutup_mesin(): void
    {
        $this->seedPrayerTimes();

        $kemarin = Carbon::yesterday();

        $tertinggal = $this->makeSession('zuhur', $kemarin->toDateString(), [
            'status' => SolatSession::StatusSedangDibuka,
            'opened_at' => Carbon::parse($kemarin->toDateString().' 12:05'),
        ]);

        $actions = $this->runAt('06:00');

        $this->assertSame(SolatSession::StatusSudahDitutup, $tertinggal->refresh()->status);
        $this->assertStringContainsString('tanggal sebelumnya', $actions[0]['message']);
        $this->assertNull($this->service()->activeSession());
    }

    public function test_waktu_solat_yang_dinonaktifkan_tidak_dibuatkan_sesi(): void
    {
        $this->seedPrayerTimes();

        PrayerTime::query()->where('prayer_name', 'ashar')->update(['is_active' => false]);

        $this->assertSame([], $this->runAt('15:06'));
        $this->assertNull($this->sessionFor('ashar'));
    }

    public function test_perubahan_jam_dari_admin_dipakai_mesin(): void
    {
        $this->seedPrayerTimes();
        $this->makeSession('zuhur');

        // Admin menggeser Zuhur menjadi 11:00 sampai 11:30.
        PrayerTime::query()->where('prayer_name', 'zuhur')->update([
            'start_time' => '11:00',
            'end_time' => '11:30',
        ]);

        // Pukul 11:20 sudah masuk jendela yang baru, padahal jam lama belum.
        $actions = $this->runAt('11:20');

        $session = $this->sessionFor();

        $this->assertSame('opened', $actions[0]['action']);
        $this->assertSame('11:00 sampai 11:30', $session->windowLabel());
        $this->assertSame(SolatSession::StatusSedangDibuka, $session->status);
    }

    public function test_perintah_menjalankan_mesin_sesi(): void
    {
        $this->seedPrayerTimes();

        $this->travelTo(Carbon::parse('today 11:55'));

        $this->artisan('solat:sesi-otomatis')
            ->expectsOutputToContain('dibuka otomatis')
            ->expectsOutputToContain('Ringkasan')
            ->assertSuccessful();

        $this->assertSame(SolatSession::StatusSedangDibuka, $this->sessionFor()->status);
    }

    public function test_perintah_diam_saat_tidak_ada_jendela_yang_berjalan(): void
    {
        $this->seedPrayerTimes();

        $this->travelTo(Carbon::parse('today 08:00'));

        $this->artisan('solat:sesi-otomatis')
            ->expectsOutputToContain('Tidak ada sesi yang perlu dibuka atau ditutup')
            ->assertSuccessful();

        $this->assertSame(0, SolatSession::query()->count());
    }

    public function test_perintah_bisa_diperiksa_pada_jam_lain(): void
    {
        $this->seedPrayerTimes();

        // Jam server sengaja berbeda dari jam yang diperiksa.
        $this->travelTo(Carbon::parse('today 08:00'));

        $this->artisan('solat:sesi-otomatis', ['--at' => Carbon::today()->toDateString().' 18:10'])
            ->expectsOutputToContain('dibuka otomatis')
            ->assertSuccessful();

        $this->assertSame(SolatSession::StatusSedangDibuka, $this->sessionFor('maghrib')->status);
        $this->assertNull($this->sessionFor('zuhur'));
    }
}
