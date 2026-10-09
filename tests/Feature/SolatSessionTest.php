<?php

namespace Tests\Feature;

use App\Models\SolatAttendanceLog;
use App\Models\SolatSession;
use App\Models\User;
use App\Services\SolatSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SolatSessionTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SolatSessionService
    {
        return app(SolatSessionService::class);
    }

    public function test_membuat_sesi_untuk_semua_waktu_solat_aktif(): void
    {
        $this->seedPrayerTimes();
        $today = Carbon::today();

        $first = $this->service()->generateForDate($today);

        $this->assertSame(5, $first['created']);
        $this->assertSame(5, $first['total']);
        $this->assertSame(5, SolatSession::query()->forDate($today)->count());

        // Menekan tombol yang sama dua kali tidak boleh menghapus atau
        // menggandakan sesi yang sudah ada.
        $second = $this->service()->generateForDate($today);

        $this->assertSame(0, $second['created']);
        $this->assertSame(5, $second['skipped']);
        $this->assertSame(5, SolatSession::query()->forDate($today)->count());
    }

    public function test_petugas_membuka_sesi_di_dalam_jendela_waktu(): void
    {
        $this->seedPrayerTimes();
        $petugas = $this->makeUser(User::RolePetugas);
        $session = $this->makeSession('zuhur');

        $this->travelTo(Carbon::parse('today 12:05'));

        $result = $this->service()->open($session, $petugas);

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('dibuka', $result['message']);

        $session->refresh();

        $this->assertSame(SolatSession::StatusSedangDibuka, $session->status);
        $this->assertSame($petugas->id, $session->opened_by);
        $this->assertNotNull($session->opened_at);
        $this->assertTrue($this->service()->activeSession()?->is($session));
    }

    public function test_petugas_boleh_membuka_sepuluh_menit_sebelum_mulai(): void
    {
        $this->seedPrayerTimes();
        $petugas = $this->makeUser(User::RolePetugas);
        $session = $this->makeSession('zuhur');

        // Zuhur mulai 12:00, jadi pukul 11:50 sudah boleh dibuka.
        $this->travelTo(Carbon::parse('today 11:50'));

        $this->assertTrue($this->service()->open($session, $petugas)['ok']);
    }

    public function test_petugas_tidak_boleh_membuka_terlalu_awal(): void
    {
        $this->seedPrayerTimes();
        $petugas = $this->makeUser(User::RolePetugas);
        $session = $this->makeSession('zuhur');

        $this->travelTo(Carbon::parse('today 11:40'));

        $result = $this->service()->open($session, $petugas);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('baru bisa dibuka 10 menit sebelum pukul 12:00', $result['message']);
        $this->assertSame(SolatSession::StatusBelumDibuka, $session->refresh()->status);
    }

    public function test_petugas_tidak_boleh_membuka_setelah_waktu_habis(): void
    {
        $this->seedPrayerTimes();
        $petugas = $this->makeUser(User::RolePetugas);
        $session = $this->makeSession('zuhur');

        // Zuhur berakhir 12:45.
        $this->travelTo(Carbon::parse('today 13:10'));

        $result = $this->service()->open($session, $petugas);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('sudah lewat pukul 12:45', $result['message']);
    }

    public function test_petugas_hanya_boleh_membuka_sesi_hari_ini(): void
    {
        $this->seedPrayerTimes();
        $petugas = $this->makeUser(User::RolePetugas);
        $session = $this->makeSession('zuhur', Carbon::yesterday()->toDateString());

        $this->travelTo(Carbon::parse('today 12:05'));

        $result = $this->service()->open($session, $petugas);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('hanya bisa membuka sesi hari ini', $result['message']);
    }

    public function test_admin_dibebaskan_dari_batas_waktu(): void
    {
        $this->seedPrayerTimes();
        $admin = $this->makeUser(User::RoleAdmin);
        $session = $this->makeSession('subuh');

        // Subuh sudah lewat jauh, tetapi admin tetap boleh merapikan sesinya.
        $this->travelTo(Carbon::parse('today 23:30'));

        $this->assertTrue($this->service()->open($session, $admin)['ok']);
    }

    public function test_hanya_satu_sesi_yang_boleh_terbuka(): void
    {
        $this->seedPrayerTimes();
        $admin = $this->makeUser(User::RoleAdmin);
        $zuhur = $this->makeSession('zuhur');
        $ashar = $this->makeSession('ashar');

        $this->travelTo(Carbon::parse('today 15:00'));

        $this->assertTrue($this->service()->open($zuhur, $admin)['ok']);

        $result = $this->service()->open($ashar, $admin);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('masih dibuka. Tutup dulu sebelum membuka sesi lain', $result['message']);
        $this->assertSame(SolatSession::StatusBelumDibuka, $ashar->refresh()->status);
    }

    public function test_sesi_yang_sudah_dibuka_tidak_dibuka_dua_kali(): void
    {
        $this->seedPrayerTimes();
        $admin = $this->makeUser(User::RoleAdmin);
        $session = $this->makeSession('zuhur');

        $this->travelTo(Carbon::parse('today 12:05'));

        $this->service()->open($session, $admin);
        $second = $this->service()->open($session, $admin);

        $this->assertFalse($second['ok']);
        $this->assertStringContainsString('sudah dibuka', $second['message']);
    }

    public function test_menutup_sesi_menghitung_catatan_kehadiran(): void
    {
        $this->seedPrayerTimes();
        $admin = $this->makeUser(User::RoleAdmin);
        $session = $this->makeSession('zuhur');
        $murid = $this->makeUser(User::RoleMurid);

        $this->travelTo(Carbon::parse('today 12:05'));
        $this->service()->open($session, $admin);

        SolatAttendanceLog::create([
            'user_id' => $murid->id,
            'solat_session_id' => $session->id,
            'scanned_at' => Carbon::now(),
            'status' => SolatAttendanceLog::StatusHadir,
        ]);

        $result = $this->service()->close($session, $admin);

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('ditutup dengan 1 catatan kehadiran', $result['message']);

        $session->refresh();

        $this->assertSame(SolatSession::StatusSudahDitutup, $session->status);
        $this->assertSame($admin->id, $session->closed_by);
        $this->assertNull($this->service()->activeSession());

        $this->assertStringContainsString('sudah ditutup', $this->service()->close($session, $admin)['message']);
    }

    public function test_halaman_sesi_absen_menampilkan_keadaan_sesi(): void
    {
        $this->seedPrayerTimes();
        $petugas = $this->makeUser(User::RolePetugas);
        $session = $this->makeSession('zuhur');

        $this->travelTo(Carbon::parse('today 12:05'));
        $this->service()->open($session, $petugas);

        $this->actingAs($petugas)
            ->get(route('sessions.index'))
            ->assertOk()
            ->assertSee('Zuhur')
            ->assertSee('Sedang dibuka');
    }

    public function test_petugas_membuka_sesi_lewat_halaman_dan_pesannya_terbaca(): void
    {
        $this->seedPrayerTimes();
        $petugas = $this->makeUser(User::RolePetugas);
        $session = $this->makeSession('zuhur');

        $this->travelTo(Carbon::parse('today 12:05'));

        $this->followingRedirects()
            ->actingAs($petugas)
            ->put(route('sessions.open', $session))
            ->assertOk()
            ->assertSee('Anggota sudah bisa absen di depan kamera');

        $this->assertSame(SolatSession::StatusSedangDibuka, $session->refresh()->status);
    }

    public function test_petugas_melihat_alasan_penolakan_saat_membuka_terlalu_awal(): void
    {
        $this->seedPrayerTimes();
        $petugas = $this->makeUser(User::RolePetugas);
        $session = $this->makeSession('isya');

        $this->travelTo(Carbon::parse('today 08:00'));

        $this->followingRedirects()
            ->actingAs($petugas)
            ->put(route('sessions.open', $session))
            ->assertOk()
            ->assertSee('baru bisa dibuka 10 menit sebelum pukul');

        $this->assertSame(SolatSession::StatusBelumDibuka, $session->refresh()->status);
    }

    public function test_membuat_sesi_lewat_halaman_menghitung_waktu_solat_aktif(): void
    {
        $this->seedPrayerTimes();
        $admin = $this->makeUser(User::RoleAdmin);

        $this->followingRedirects()
            ->actingAs($admin)
            ->post(route('sessions.generate'), ['date' => Carbon::today()->toDateString()])
            ->assertOk()
            ->assertSee('5 sesi dibuat')
            ->assertSee('Total 5 waktu solat aktif');

        $this->assertSame(5, SolatSession::query()->forDate(Carbon::today())->count());
    }
}
