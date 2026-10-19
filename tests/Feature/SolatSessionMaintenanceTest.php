<?php

namespace Tests\Feature;

use App\Models\SolatAttendanceLog;
use App\Models\SolatSession;
use App\Models\User;
use App\Services\SolatProofService;
use App\Services\SolatSessionService;
use Carbon\CarbonInterface;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Merapikan sesi absen.
 *
 * Sesi bisa dibuat keliru, misalnya untuk tanggal yang salah, sehingga admin
 * perlu jalan untuk menghapusnya: satu sesi, beberapa sesi yang dicentang, atau
 * seluruh sesi pada satu tanggal. Yang dijaga di sini: catatan kehadiran dan
 * foto buktinya ikut terhapus, sesi yang sedang dibuka tidak pernah terhapus
 * diam-diam, dan sesi uji coba hanya bisa dihapus lewat panel mode uji coba.
 */
class SolatSessionMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    /** Isi berkas bukti yang dikirim mesin wajah pada pengujian. */
    private const ProofBytes = "\xFF\xD8\xFF\xE0potret uji";

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(SolatProofService::Disk);
    }

    private function service(): SolatSessionService
    {
        return app(SolatSessionService::class);
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk(SolatProofService::Disk);
    }

    /**
     * Catatan kehadiran lengkap dengan foto buktinya, tanpa lewat kamera.
     */
    private function logWithProof(SolatSession $session, ?CarbonInterface $at = null): SolatAttendanceLog
    {
        $moment = $at !== null ? Carbon::parse($at) : Carbon::now();
        $path = app(SolatProofService::class)->store(self::ProofBytes, $session, $moment);

        $this->assertNotNull($path, 'Berkas bukti uji gagal disimpan.');

        return SolatAttendanceLog::create([
            'user_id' => $this->makeUser(User::RoleMurid)->getKey(),
            'solat_session_id' => $session->getKey(),
            'scanned_at' => $moment,
            'status' => SolatAttendanceLog::StatusHadir,
            'device_id' => 'kamera-wajah',
            'proof_photo_path' => $path,
        ]);
    }

    public function test_admin_menghapus_satu_sesi_beserta_catatan_dan_foto_buktinya(): void
    {
        $this->seedPrayerTimes();
        $admin = $this->makeUser(User::RoleAdmin);
        $session = $this->makeSession('zuhur');
        $log = $this->logWithProof($session);

        $this->followingRedirects()
            ->actingAs($admin)
            ->delete(route('sessions.destroy', $session))
            ->assertOk()
            ->assertSee('dihapus beserta catatan kehadirannya');

        $this->assertDatabaseMissing('solat_sessions', ['id' => $session->id]);
        $this->assertDatabaseMissing('solat_attendance_logs', ['id' => $log->id]);
        $this->disk()->assertMissing($log->proof_photo_path);
    }

    public function test_sesi_yang_sedang_dibuka_ikut_terhapus_dan_status_aktif_bersih(): void
    {
        $this->seedPrayerTimes();
        $admin = $this->makeUser(User::RoleAdmin);
        $session = $this->makeSession('zuhur');

        $this->travelTo(Carbon::parse('today 12:05'));
        $this->assertTrue($this->service()->open($session, $admin)['ok']);
        $this->assertNotNull($this->service()->activeSession());

        $this->followingRedirects()
            ->actingAs($admin)
            ->delete(route('sessions.destroy', $session))
            ->assertOk()
            ->assertSee('dihapus beserta catatan kehadirannya');

        $this->assertDatabaseMissing('solat_sessions', ['id' => $session->id]);
        $this->assertNull($this->service()->activeSession());
    }

    public function test_admin_menghapus_sesi_yang_dicentang(): void
    {
        $this->seedPrayerTimes();
        $admin = $this->makeUser(User::RoleAdmin);
        $zuhur = $this->makeSession('zuhur');
        $ashar = $this->makeSession('ashar');
        $isya = $this->makeSession('isya');

        $this->logWithProof($zuhur);
        $this->logWithProof($ashar, Carbon::parse('today 15:20'));
        $this->logWithProof($isya, Carbon::parse('today 19:10'));

        $this->followingRedirects()
            ->actingAs($admin)
            ->delete(route('sessions.destroy-selected'), [
                'sessions' => [$zuhur->id, $ashar->id],
                'date' => Carbon::today()->toDateString(),
            ])
            ->assertOk()
            ->assertSee('2 sesi dihapus beserta catatan kehadirannya');

        $this->assertDatabaseMissing('solat_sessions', ['id' => $zuhur->id]);
        $this->assertDatabaseMissing('solat_sessions', ['id' => $ashar->id]);

        // Sesi yang tidak dicentang harus tetap utuh bersama catatannya.
        $this->assertDatabaseHas('solat_sessions', ['id' => $isya->id]);
        $this->assertSame(1, SolatAttendanceLog::query()->count());
    }

    public function test_mencentang_sesi_yang_sedang_dibuka_ikut_terhapus(): void
    {
        $this->seedPrayerTimes();
        $admin = $this->makeUser(User::RoleAdmin);
        $zuhur = $this->makeSession('zuhur');
        $ashar = $this->makeSession('ashar');

        $this->travelTo(Carbon::parse('today 12:05'));
        $this->assertTrue($this->service()->open($zuhur, $admin)['ok']);

        $this->followingRedirects()
            ->actingAs($admin)
            ->delete(route('sessions.destroy-selected'), [
                'sessions' => [$zuhur->id, $ashar->id],
                'date' => Carbon::today()->toDateString(),
            ])
            ->assertOk()
            ->assertSee('2 sesi dihapus beserta catatan kehadirannya');

        $this->assertDatabaseMissing('solat_sessions', ['id' => $zuhur->id]);
        $this->assertDatabaseMissing('solat_sessions', ['id' => $ashar->id]);
        $this->assertNull($this->service()->activeSession());
    }

    public function test_sesi_tertinggal_dari_tanggal_sebelumnya_otomatis_ditutup_saat_memeriksa_sesi_aktif_atau_mode_uji(): void
    {
        $this->seedPrayerTimes();
        $admin = $this->makeUser(User::RoleAdmin);
        $kemarin = Carbon::yesterday()->toDateString();

        $stale = $this->makeSession('zuhur', $kemarin, [
            'status' => SolatSession::StatusSedangDibuka,
            'opened_at' => Carbon::parse($kemarin.' 12:05'),
        ]);

        // Tanpa menjalankan perintah konsol penjadwal, activeSession() langsung menutup sesi kemarin
        $this->assertNull($this->service()->activeSession());
        $this->assertSame(SolatSession::StatusSudahDitutup, $stale->refresh()->status);

        // Mode uji coba langsung bisa dinyalakan tanpa terhalang sesi kemarin
        $result = $this->service()->startTestSession($admin);
        $this->assertTrue($result['ok']);
    }

    public function test_admin_menghapus_semua_sesi_pada_satu_tanggal(): void
    {
        $this->seedPrayerTimes();
        $admin = $this->makeUser(User::RoleAdmin);
        $zuhur = $this->makeSession('zuhur');
        $ashar = $this->makeSession('ashar');
        $besok = $this->makeSession('subuh', Carbon::today()->addDay()->toDateString());

        $this->logWithProof($zuhur);
        $this->logWithProof($ashar, Carbon::parse('today 15:20'));

        $this->followingRedirects()
            ->actingAs($admin)
            ->delete(route('sessions.destroy-day'), ['date' => Carbon::today()->toDateString()])
            ->assertOk()
            ->assertSee('2 sesi dihapus beserta catatan kehadirannya');

        $this->assertSame(0, SolatSession::query()->forDate(Carbon::today())->count());
        $this->assertSame(0, SolatAttendanceLog::query()->count());

        // Sesi tanggal lain tidak boleh tersentuh.
        $this->assertDatabaseHas('solat_sessions', ['id' => $besok->id]);
    }

    public function test_menghapus_tanggal_yang_tidak_punya_sesi_memberi_tahu(): void
    {
        $this->seedPrayerTimes();
        $admin = $this->makeUser(User::RoleAdmin);
        $this->makeSession('zuhur');

        $this->followingRedirects()
            ->actingAs($admin)
            ->delete(route('sessions.destroy-day'), ['date' => Carbon::today()->addDays(4)->toDateString()])
            ->assertOk()
            ->assertSee('Tidak ada sesi pada tanggal');

        $this->assertSame(1, SolatSession::query()->count());
    }

    public function test_menghapus_tanpa_centang_ditolak_dengan_pesan_yang_terbaca(): void
    {
        $this->seedPrayerTimes();
        $admin = $this->makeUser(User::RoleAdmin);
        $this->makeSession('zuhur');

        // Halaman tujuan dikunci supaya pesan galat isian benar-benar terbaca
        // pada halaman daftar sesi, bukan pada halaman lain.
        $this->from(route('sessions.index'))
            ->followingRedirects()
            ->actingAs($admin)
            ->delete(route('sessions.destroy-selected'), ['date' => Carbon::today()->toDateString()])
            ->assertOk()
            ->assertSee('Centang dulu sesi yang ingin dihapus');

        $this->assertSame(1, SolatSession::query()->count());
    }

    public function test_petugas_tidak_boleh_menghapus_sesi(): void
    {
        $this->seedPrayerTimes();
        $petugas = $this->makeUser(User::RolePetugas);
        $session = $this->makeSession('zuhur');

        $this->actingAs($petugas)
            ->deleteJson(route('sessions.destroy-day'), ['date' => Carbon::today()->toDateString()])
            ->assertForbidden()
            ->assertJson(['display_message' => 'Tidak diizinkan']);

        $this->assertDatabaseHas('solat_sessions', ['id' => $session->id]);
    }

    public function test_tombol_hapus_hanya_tampil_untuk_admin(): void
    {
        $this->seedPrayerTimes();
        $this->makeSession('zuhur');

        $petugas = $this->makeUser(User::RolePetugas);

        $this->actingAs($petugas)
            ->get(route('sessions.index'))
            ->assertOk()
            ->assertDontSee('Hapus yang dicentang')
            ->assertDontSee('Hapus sesi tanggal ini')
            ->assertDontSee('Mode uji coba absen');

        $admin = $this->makeUser(User::RoleAdmin);

        $this->actingAs($admin)
            ->get(route('sessions.index'))
            ->assertOk()
            ->assertSee('Hapus yang dicentang')
            ->assertSee('Hapus sesi tanggal ini')
            ->assertSee('Mode uji coba absen');
    }

    public function test_sesi_uji_coba_tidak_bisa_dihapus_lewat_daftar_sesi(): void
    {
        $this->seedPrayerTimes();
        $admin = $this->makeUser(User::RoleAdmin);

        $this->assertTrue($this->service()->startTestSession($admin)['ok']);
        $test = SolatSession::query()->test()->firstOrFail();

        $this->followingRedirects()
            ->actingAs($admin)
            ->delete(route('sessions.destroy', $test))
            ->assertOk()
            ->assertSee('Sesi uji coba dihapus dari panel mode uji coba');

        $this->assertDatabaseHas('solat_sessions', ['id' => $test->id]);
    }

    public function test_menghapus_satu_tanggal_menyapu_semua_sesi_hari_itu(): void
    {
        $this->seedPrayerTimes();
        $this->makeSession('zuhur');
        $this->makeSession('ashar');

        $this->service()->generateForDate(Carbon::today());

        $this->assertSame(5, SolatSession::query()->forDate(Carbon::today())->count());

        $this->assertTrue($this->service()->deleteForDate(Carbon::today())['ok']);

        $this->assertSame(0, SolatSession::query()->forDate(Carbon::today())->count());
    }

    public function test_sesi_yang_sudah_ditutup_bisa_dihapus(): void
    {
        $this->seedPrayerTimes();
        $admin = $this->makeUser(User::RoleAdmin);
        $session = $this->makeSession('zuhur');
        $log = $this->logWithProof($session);

        $this->travelTo(Carbon::parse('today 12:05'));
        $this->assertTrue($this->service()->open($session, $admin)['ok']);
        $this->assertTrue($this->service()->close($session->refresh(), $admin)['ok']);

        $result = $this->service()->delete($session->refresh());

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['deleted']);
        $this->assertDatabaseMissing('solat_sessions', ['id' => $session->id]);
        $this->assertDatabaseMissing('solat_attendance_logs', ['id' => $log->id]);
    }
}
