<?php

namespace Tests\Feature;

use App\Models\FaceEmbedding;
use App\Models\SolatAttendanceLog;
use App\Models\SolatSession;
use App\Models\User;
use App\Services\SolatAttendanceService;
use App\Services\SolatProofService;
use App\Services\SolatReportService;
use App\Services\SolatSessionService;
use Carbon\CarbonInterface;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Mode uji coba absen.
 *
 * Admin perlu mencoba kamera absen wajah tanpa harus membuka sesi waktu solat
 * yang sungguhan. Mode uji coba memakai tabel sesi yang sama supaya catatan dan
 * foto bukti memakai jalan yang sudah ada, tetapi sengaja tidak pernah dihitung
 * pada rekap mana pun dan memberi jeda sebelum orang yang sama mencoba lagi.
 */
class SolatTestModeTest extends TestCase
{
    use RefreshDatabase;

    /** Isi berkas bukti yang dikirim mesin wajah pada pengujian. */
    private const ProofBytes = "\xFF\xD8\xFF\xE0potret uji";

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(SolatProofService::Disk);
    }

    private function attendance(): SolatAttendanceService
    {
        return app(SolatAttendanceService::class);
    }

    private function sessions(): SolatSessionService
    {
        return app(SolatSessionService::class);
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk(SolatProofService::Disk);
    }

    /**
     * Anggota yang wajahnya sudah terdaftar dan cocok dengan jawaban layanan uji.
     */
    private function enrolledMember(): User
    {
        $member = $this->makeUser(User::RoleMurid, [
            'enrollment_status' => User::EnrollmentCompleted,
            'enrolled_at' => now(),
        ]);

        FaceEmbedding::create([
            'user_id' => $member->id,
            'label' => 'Daftar uji',
            'model' => FaceEmbedding::DefaultModel,
            'embedding' => array_fill(0, FaceEmbedding::Dimensions, 0.01),
        ]);

        return $member;
    }

    /**
     * Nyalakan mode uji coba seperti admin, lalu kembalikan sesinya.
     */
    private function startTest(User $admin): SolatSession
    {
        $this->assertTrue($this->sessions()->startTestSession($admin)['ok']);

        return SolatSession::query()->test()->firstOrFail();
    }

    private function petugas(): User
    {
        return $this->makeUser(User::RolePetugas, ['can_scan_face' => true]);
    }

    /**
     * Catatan percobaan lengkap dengan foto buktinya, tanpa lewat kamera.
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

    public function test_admin_menyalakan_dan_mematikan_mode_uji_coba(): void
    {
        $this->seedPrayerTimes();
        $admin = $this->makeUser(User::RoleAdmin);

        $this->followingRedirects()
            ->actingAs($admin)
            ->post(route('sessions.test.start'))
            ->assertOk()
            ->assertSee('Mode uji coba menyala');

        $test = SolatSession::query()->test()->firstOrFail();

        $this->assertTrue($test->isTest());
        $this->assertTrue($test->isOpen());
        $this->assertSame('Uji coba', $test->prayerLabel());
        $this->assertSame($test->id, $this->sessions()->testSession()?->id);
        $this->assertNull($this->sessions()->activeSession());

        $this->followingRedirects()
            ->actingAs($admin)
            ->put(route('sessions.test.stop'))
            ->assertOk()
            ->assertSee('Mode uji coba dimatikan dengan 0 catatan percobaan');

        $this->assertNull($this->sessions()->testSession());
        $this->assertSame(1, SolatSession::query()->test()->count());
        $this->assertSame(SolatSession::StatusSudahDitutup, $test->refresh()->status);
    }

    public function test_mode_uji_coba_tidak_bisa_menyala_saat_sesi_solat_sedang_dibuka(): void
    {
        $this->seedPrayerTimes();
        $admin = $this->makeUser(User::RoleAdmin);
        $session = $this->makeSession('zuhur');

        $this->travelTo(Carbon::parse('today 12:05'));
        $this->assertTrue($this->sessions()->open($session, $admin)['ok']);

        $this->followingRedirects()
            ->actingAs($admin)
            ->post(route('sessions.test.start'))
            ->assertOk()
            ->assertSee('masih dibuka. Tutup dulu sebelum menyalakan mode uji coba');

        $this->assertSame(0, SolatSession::query()->test()->count());
    }

    public function test_kamera_petugas_bisa_mencatat_percobaan(): void
    {
        $this->seedPrayerTimes();
        $admin = $this->makeUser(User::RoleAdmin);
        $test = $this->startTest($admin);
        $member = $this->enrolledMember();

        $this->fakeFaceService(photo: self::ProofBytes);

        $this->actingAs($this->petugas())
            ->post(route('attendance.wajah.scan'), ['photo' => UploadedFile::fake()->image('absen.jpg')])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'status' => SolatAttendanceService::StatusSuccessMulti,
            ])
            ->assertJsonPath('faces.0.status', SolatAttendanceService::StatusRecorded)
            ->assertJsonPath('faces.0.attendance.display_message', 'Absen tersimpan')
            ->assertJsonPath('faces.0.attendance.message', fn (mixed $message): bool => is_string($message)
                && str_contains($message, 'tidak dihitung pada rekap'));

        $log = SolatAttendanceLog::query()->firstOrFail();

        $this->assertSame($test->id, $log->solat_session_id);
        $this->assertSame($member->id, $log->user_id);
        $this->assertNotNull($log->proof_photo_path);
    }

    public function test_perangkat_gerbang_tetap_ditolak_saat_mode_uji_coba_menyala(): void
    {
        $this->seedPrayerTimes();
        $admin = $this->makeUser(User::RoleAdmin);
        $this->startTest($admin);
        $this->enrolledMember();
        $this->fakeFaceService();

        // Perangkat gerbang tidak pernah boleh memakai sesi uji coba.
        $result = $this->attendance()->recordFromImage('potret', 'gerbang', Carbon::now());

        $this->assertFalse($result['ok']);
        $this->assertSame(SolatAttendanceService::StatusNoSession, $result['status']);
        $this->assertSame('Sesi belum buka', $result['display_message']);
        $this->assertSame(0, SolatAttendanceLog::query()->count());
    }

    public function test_orang_yang_sama_menunggu_jeda_sebelum_bisa_mencoba_lagi(): void
    {
        config(['solat.test_cooldown_minutes' => 4]);

        $this->seedPrayerTimes();
        $admin = $this->makeUser(User::RoleAdmin);
        $this->enrolledMember();
        $this->fakeFaceService();

        $this->travelTo(Carbon::parse('today 12:00'));
        $this->startTest($admin);

        $this->assertTrue($this->attendance()->recordFromImage('potret', 'kamera-wajah', Carbon::now(), true)['ok']);

        $this->travelTo(Carbon::parse('today 12:01'));
        $result = $this->attendance()->recordFromImage('potret', 'kamera-wajah', Carbon::now(), true);

        $this->assertFalse($result['ok']);
        $this->assertSame(SolatAttendanceService::StatusDuplicate, $result['status']);
        $this->assertSame('Jeda pindai', $result['label']);
        $this->assertSame('Tunggu 3 menit', $result['display_message']);
        $this->assertStringContainsString('Jeda mode uji coba masih sekitar 3 menit', $result['message']);
        $this->assertSame(1, SolatAttendanceLog::query()->count());
    }

    public function test_setelah_jeda_lewat_percobaan_memperbarui_catatan_yang_sama(): void
    {
        config(['solat.test_cooldown_minutes' => 4]);

        $this->seedPrayerTimes();
        $admin = $this->makeUser(User::RoleAdmin);
        $this->enrolledMember();
        $this->fakeFaceService();

        $this->travelTo(Carbon::parse('today 12:00'));
        $this->startTest($admin);
        $this->attendance()->recordFromImage('potret', 'kamera-wajah', Carbon::now(), true);

        $this->travelTo(Carbon::parse('today 12:05'));
        $result = $this->attendance()->recordFromImage('potret', 'kamera-wajah', Carbon::now(), true);

        $this->assertTrue($result['ok']);
        $this->assertSame(SolatAttendanceService::StatusRecorded, $result['status']);
        $this->assertSame('Uji coba OK', $result['display_message']);
        $this->assertStringContainsString('diperbarui pukul 12:05', $result['message']);

        $this->assertSame(1, SolatAttendanceLog::query()->count());
        $this->assertSame('12:05', SolatAttendanceLog::query()->firstOrFail()->scanned_at?->format('H:i'));
    }

    public function test_setelah_jeda_lewat_percobaan_memperbarui_foto_bukti_dan_menghapus_foto_lama(): void
    {
        config(['solat.test_cooldown_minutes' => 4]);

        $this->seedPrayerTimes();
        $admin = $this->makeUser(User::RoleAdmin);
        $this->enrolledMember();

        // Pindai pertama pukul 12:00 membawa bukti lama
        $this->fakeFaceService(photo: self::ProofBytes);
        $this->travelTo(Carbon::parse('today 12:00'));
        $this->startTest($admin);

        $firstResult = $this->attendance()->recordFacesFromImage('potret 1', 'kamera-wajah', Carbon::now(), true);
        $this->assertTrue($firstResult['ok']);

        $log = SolatAttendanceLog::query()->firstOrFail();
        $oldPath = $log->proof_photo_path;
        $this->assertNotNull($oldPath);
        $this->assertTrue($this->disk()->exists($oldPath));
        $this->assertStringContainsString((string) $log->scanned_at->timestamp, $firstResult['proof_url']);

        // Pindai kedua 5 menit kemudian membawa bukti baru
        $newProof = "\xFF\xD8\xFF\xE0potret baru";
        $this->fakeFaceService(photo: $newProof);
        $this->travelTo(Carbon::parse('today 12:05'));

        $secondResult = $this->attendance()->recordFacesFromImage('potret 2', 'kamera-wajah', Carbon::now(), true);
        $this->assertTrue($secondResult['ok']);
        $this->assertSame(1, SolatAttendanceLog::query()->count());

        $log->refresh();
        $newPath = $log->proof_photo_path;
        $this->assertNotNull($newPath);
        $this->assertNotSame($oldPath, $newPath);
        $this->assertTrue($this->disk()->exists($newPath));
        $this->assertFalse($this->disk()->exists($oldPath));
        $this->assertSame($newProof, $this->disk()->get($newPath));
        $this->assertStringContainsString((string) $log->scanned_at->timestamp, $secondResult['proof_url']);
    }

    public function test_jeda_bisa_dimatikan_lewat_pengaturan(): void
    {
        config(['solat.test_cooldown_minutes' => 0]);

        $this->seedPrayerTimes();
        $admin = $this->makeUser(User::RoleAdmin);
        $this->enrolledMember();
        $this->fakeFaceService();

        $this->travelTo(Carbon::parse('today 12:00'));
        $this->startTest($admin);

        $this->assertTrue($this->attendance()->recordFromImage('potret', 'kamera-wajah', Carbon::now(), true)['ok']);

        $result = $this->attendance()->recordFromImage('potret', 'kamera-wajah', Carbon::now(), true);

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('diperbarui', $result['message']);
        $this->assertSame(1, SolatAttendanceLog::query()->count());
    }

    public function test_admin_menghapus_sesi_uji_coba_beserta_catatan_dan_foto_buktinya(): void
    {
        $this->seedPrayerTimes();
        $admin = $this->makeUser(User::RoleAdmin);
        $test = $this->startTest($admin);

        $first = $this->logWithProof($test);
        $second = $this->logWithProof($test, Carbon::parse('today 12:10'));

        $this->assertSame(2, $test->logs()->count());

        $this->followingRedirects()
            ->actingAs($admin)
            ->delete(route('sessions.test.destroy'))
            ->assertOk()
            ->assertSee('Sesi uji coba dihapus bersama 2 catatan percobaan dan foto buktinya');

        $this->assertSame(0, SolatSession::query()->test()->count());
        $this->assertSame(0, SolatAttendanceLog::query()->count());
        $this->disk()->assertMissing($first->proof_photo_path);
        $this->disk()->assertMissing($second->proof_photo_path);
    }

    public function test_menghapus_saat_mode_uji_coba_belum_ada_memberi_tahu(): void
    {
        $this->seedPrayerTimes();
        $admin = $this->makeUser(User::RoleAdmin);

        $this->followingRedirects()
            ->actingAs($admin)
            ->delete(route('sessions.test.destroy'))
            ->assertOk()
            ->assertSee('Belum ada sesi uji coba yang bisa dihapus');

        $this->assertSame(0, SolatSession::query()->test()->count());
    }

    public function test_halaman_sesi_menampilkan_panel_mode_uji_coba(): void
    {
        $this->seedPrayerTimes();
        $admin = $this->makeUser(User::RoleAdmin);
        $this->makeSession('zuhur');

        $this->actingAs($admin)
            ->get(route('sessions.index'))
            ->assertOk()
            ->assertSee('Mode uji coba absen')
            ->assertSee('Nyalakan mode uji coba');

        $this->startTest($admin);

        $this->actingAs($admin)
            ->get(route('sessions.index'))
            ->assertOk()
            ->assertSee('Mode uji coba menyala')
            ->assertSee('Matikan mode uji coba')
            ->assertSee('Hapus sesi uji coba');
    }

    public function test_halaman_absen_wajah_memberi_tahu_mode_uji_coba(): void
    {
        $this->seedPrayerTimes();
        $admin = $this->makeUser(User::RoleAdmin);
        $this->startTest($admin);
        $this->fakeFaceService();

        $this->actingAs($this->petugas())
            ->get(route('attendance.wajah'))
            ->assertOk()
            ->assertSee('Kamera siap menerima percobaan')
            ->assertSee('Mode uji coba menyala')
            ->assertSee('tidak dihitung pada rekap')
            ->assertSee('anggota yang ditugaskan memindai wajah boleh ikut mencoba');
    }

    public function test_catatan_uji_coba_tidak_masuk_rekap_dan_ringkasan(): void
    {
        $this->seedPrayerTimes();
        $admin = $this->makeUser(User::RoleAdmin);
        $real = $this->makeSession('zuhur');
        $this->logWithProof($real, Carbon::parse('today 12:05'));

        $test = $this->startTest($admin);
        $this->logWithProof($test, Carbon::parse('today 12:10'));

        $report = app(SolatReportService::class);
        $summary = $report->summary(Carbon::today(), Carbon::today());

        $this->assertSame(1, $summary['sessions']);
        $this->assertSame(1, $summary['logs']);
        $this->assertSame(0, $summary['open_sessions']);

        $breakdown = $report->sessionBreakdown(Carbon::today(), Carbon::today());

        $this->assertCount(1, $breakdown);
        $this->assertSame($real->id, $breakdown->first()->id);

        $response = $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $stats = $response->viewData('stats');

        $this->assertSame(1, $stats['sesi']);
        $this->assertSame(1, $stats['absen']);
        $this->assertCount(1, $response->viewData('recent'));
    }

    public function test_anggota_yang_hanya_punya_catatan_uji_coba_dihitung_nol(): void
    {
        $this->seedPrayerTimes();
        $admin = $this->makeUser(User::RoleAdmin);
        $test = $this->startTest($admin);
        $member = $this->makeUser(User::RoleMurid);

        SolatAttendanceLog::create([
            'user_id' => $member->id,
            'solat_session_id' => $test->id,
            'scanned_at' => Carbon::now(),
            'status' => SolatAttendanceLog::StatusHadir,
            'device_id' => 'kamera-wajah',
        ]);

        $this->assertSame(0, app(SolatReportService::class)->memberHistory($member)->total());

        $response = $this->actingAs($admin)->get(route('members.index'))->assertOk();
        $row = $response->viewData('members')->getCollection()->firstWhere('id', $member->id);

        $this->assertNotNull($row);
        $this->assertSame(0, $row->attendance_logs_count);
    }
}
