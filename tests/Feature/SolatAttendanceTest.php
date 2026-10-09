<?php

namespace Tests\Feature;

use App\Models\FaceEmbedding;
use App\Models\SolatAttendanceLog;
use App\Models\SolatSession;
use App\Models\User;
use App\Services\SolatAttendanceService;
use App\Services\SolatSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SolatAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function attendance(): SolatAttendanceService
    {
        return app(SolatAttendanceService::class);
    }

    /**
     * Anggota yang wajahnya sudah terdaftar dan cocok dengan jawaban layanan uji.
     */
    private function enrolledMember(): User
    {
        $member = $this->makeUser(User::RoleMurid);

        FaceEmbedding::create([
            'user_id' => $member->id,
            'label' => 'Daftar uji',
            'model' => FaceEmbedding::DefaultModel,
            'embedding' => array_fill(0, FaceEmbedding::Dimensions, 0.01),
        ]);

        return $member;
    }

    /**
     * Buka sesi zuhur pada pukul 12:05 seperti petugas sungguhan.
     */
    private function openZuhur(): SolatSession
    {
        $admin = $this->makeUser(User::RoleAdmin);
        $session = $this->makeSession('zuhur');

        $this->travelTo(Carbon::parse('today 12:05'));
        $result = app(SolatSessionService::class)->open($session, $admin);

        $this->assertTrue($result['ok']);

        return $session->refresh();
    }

    public function test_pindai_pada_sesi_terbuka_mencatat_hadir(): void
    {
        $this->seedPrayerTimes();
        $member = $this->enrolledMember();
        $session = $this->openZuhur();
        $this->fakeFaceService();

        $result = $this->attendance()->recordFromImage('potret', 'kamera-wajah', Carbon::now());

        $this->assertTrue($result['ok']);
        $this->assertSame(SolatAttendanceService::StatusRecorded, $result['status']);
        $this->assertSame('Absen tersimpan', $result['display_message']);

        $log = SolatAttendanceLog::query()->firstOrFail();

        $this->assertSame($member->id, $log->user_id);
        $this->assertSame($session->id, $log->solat_session_id);
        $this->assertSame(SolatAttendanceLog::StatusHadir, $log->status);
        $this->assertSame('kamera-wajah', $log->device_id);
    }

    public function test_pindai_setelah_toleransi_dicatat_terlambat(): void
    {
        $this->seedPrayerTimes();
        $this->enrolledMember();
        $this->openZuhur();
        $this->fakeFaceService();

        // Zuhur mulai 12:00 dengan toleransi 10 menit.
        $this->travelTo(Carbon::parse('today 12:34'));

        $result = $this->attendance()->recordFromImage('potret', 'kamera-wajah', Carbon::now());

        $this->assertTrue($result['ok']);
        $this->assertSame(SolatAttendanceService::StatusLate, $result['status']);
        $this->assertSame('Terlambat', $result['display_message']);
        $this->assertSame(SolatAttendanceLog::StatusTerlambat, SolatAttendanceLog::query()->firstOrFail()->status);
    }

    public function test_pindai_ulang_pada_sesi_yang_sama_ditolak(): void
    {
        $this->seedPrayerTimes();
        $member = $this->enrolledMember();
        $this->openZuhur();
        $this->fakeFaceService();

        $this->attendance()->recordFromImage('potret', 'kamera-wajah', Carbon::now());

        $this->travelTo(Carbon::parse('today 12:30'));

        $result = $this->attendance()->recordFromImage('potret', 'kamera-wajah', Carbon::now());

        $this->assertFalse($result['ok']);
        $this->assertSame(SolatAttendanceService::StatusDuplicate, $result['status']);
        $this->assertSame('Sudah absen', $result['display_message']);
        $this->assertStringContainsString($member->name.' sudah tercatat', $result['message']);
        $this->assertSame(1, SolatAttendanceLog::query()->count());
    }

    public function test_pindai_tanpa_sesi_terbuka_ditolak(): void
    {
        $this->seedPrayerTimes();
        $this->makeSession('zuhur');
        $this->fakeFaceService();

        $result = $this->attendance()->recordFromImage('potret', 'kamera-wajah', Carbon::now());

        $this->assertFalse($result['ok']);
        $this->assertSame(SolatAttendanceService::StatusNoSession, $result['status']);
        $this->assertSame('Sesi belum buka', $result['display_message']);
        $this->assertSame(0, SolatAttendanceLog::query()->count());
    }

    public function test_wajah_yang_belum_terdaftar_ditolak(): void
    {
        $this->seedPrayerTimes();
        $this->openZuhur();
        $this->fakeFaceService();

        // Belum ada satu pun sidik wajah tersimpan.
        $result = $this->attendance()->recordFromImage('potret', 'kamera-wajah', Carbon::now());

        $this->assertFalse($result['ok']);
        $this->assertSame(SolatAttendanceService::StatusUnknown, $result['status']);
        $this->assertSame('Belum terdaftar', $result['display_message']);
        $this->assertSame(0, SolatAttendanceLog::query()->count());
    }

    public function test_lebih_dari_satu_wajah_ditolak(): void
    {
        $this->seedPrayerTimes();
        $this->enrolledMember();
        $this->openZuhur();
        $this->fakeFaceService(faceCount: 2);

        $result = $this->attendance()->recordFromImage('potret', 'kamera-wajah', Carbon::now());

        $this->assertFalse($result['ok']);
        $this->assertSame(SolatAttendanceService::StatusCrowded, $result['status']);
        $this->assertSame('Satu wajah saja', $result['display_message']);
    }

    public function test_wajah_yang_tidak_terbaca_ditolak(): void
    {
        $this->seedPrayerTimes();
        $this->openZuhur();
        $this->fakeFaceService(faceCount: 0);

        $result = $this->attendance()->recordFromImage('potret', 'kamera-wajah', Carbon::now());

        $this->assertFalse($result['ok']);
        $this->assertSame(SolatAttendanceService::StatusNoFace, $result['status']);
        $this->assertSame('Wajah tak jelas', $result['display_message']);
    }

    public function test_layanan_wajah_yang_mati_ditolak(): void
    {
        $this->seedPrayerTimes();
        $this->openZuhur();
        $this->fakeFaceService(available: false);

        $result = $this->attendance()->recordFromImage('potret', 'kamera-wajah', Carbon::now());

        $this->assertFalse($result['ok']);
        $this->assertSame(SolatAttendanceService::StatusUnavailable, $result['status']);
        $this->assertSame('Layanan mati', $result['display_message']);
    }

    public function test_petugas_kamera_menyimpan_absen_dari_halaman(): void
    {
        $this->seedPrayerTimes();
        $petugas = $this->makeUser(User::RolePetugas, ['can_scan_face' => true]);
        $member = $this->enrolledMember();
        $this->openZuhur();
        $this->fakeFaceService();

        $this->actingAs($petugas)
            ->post(route('attendance.wajah.scan'), ['photo' => UploadedFile::fake()->image('absen.jpg')])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'status' => SolatAttendanceService::StatusRecorded,
                'display_message' => 'Absen tersimpan',
            ]);

        $this->assertSame($member->id, SolatAttendanceLog::query()->firstOrFail()->user_id);
    }

    public function test_halaman_absen_menolak_anggota_yang_bukan_petugas(): void
    {
        $this->seedPrayerTimes();
        $murid = $this->makeUser(User::RoleMurid);
        $this->fakeFaceService();

        $this->actingAs($murid)
            ->postJson(route('attendance.wajah.scan'), [])
            ->assertForbidden()
            ->assertJson(['display_message' => 'Bukan petugas']);
    }

    public function test_halaman_absen_memberi_tahu_belum_ada_sesi(): void
    {
        $this->seedPrayerTimes();
        $petugas = $this->makeUser(User::RolePetugas, ['can_scan_face' => true]);
        $this->fakeFaceService();

        $this->actingAs($petugas)
            ->get(route('attendance.wajah'))
            ->assertOk()
            ->assertSee('Belum ada sesi yang dibuka');
    }

    public function test_halaman_absen_memberi_tahu_layanan_wajah_mati(): void
    {
        $this->seedPrayerTimes();
        $petugas = $this->makeUser(User::RolePetugas, ['can_scan_face' => true]);
        $this->openZuhur();
        $this->fakeFaceService(available: false);

        $this->actingAs($petugas)
            ->get(route('attendance.wajah'))
            ->assertOk()
            ->assertSee('Layanan wajah tidak menyala')
            ->assertSee('Layanan wajah di server belum aktif');
    }

    public function test_halaman_absen_menyebut_sesi_yang_sedang_dibuka(): void
    {
        $this->seedPrayerTimes();
        $petugas = $this->makeUser(User::RolePetugas, ['can_scan_face' => true]);
        $this->openZuhur();
        $this->fakeFaceService();

        $this->actingAs($petugas)
            ->get(route('attendance.wajah'))
            ->assertOk()
            ->assertSee('Sesi Zuhur sedang dibuka')
            ->assertSee('12:00 sampai 12:45');
    }
}
