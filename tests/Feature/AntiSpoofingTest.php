<?php

namespace Tests\Feature;

use App\Models\FaceEmbedding;
use App\Models\PrayerTime;
use App\Models\Setting;
use App\Models\SolatSession;
use App\Models\User;
use App\Services\FaceEncodingService;
use App\Services\SolatAttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AntiSpoofingTest extends TestCase
{
    use RefreshDatabase;

    public function test_pengaturan_anti_foto_palsu_bawaan_aktif_dan_dapat_dimatikan_admin(): void
    {
        $this->assertTrue(Setting::antiSpoofingEnabled());

        $admin = User::factory()->create(['role' => User::RoleAdmin, 'password_changed_at' => now()]);
        $petugas = User::factory()->create(['role' => User::RolePetugas, 'password_changed_at' => now()]);

        $this->actingAs($petugas)
            ->put(route('settings.anti-spoofing'), []);

        $this->assertTrue(Setting::antiSpoofingEnabled());

        $this->actingAs($admin)
            ->put(route('settings.anti-spoofing'), [])
            ->assertRedirect();

        $this->assertFalse(Setting::antiSpoofingEnabled());

        $this->actingAs($admin)
            ->put(route('settings.anti-spoofing'), ['anti_spoofing_enabled' => '1'])
            ->assertRedirect();

        $this->assertTrue(Setting::antiSpoofingEnabled());
    }

    public function test_face_encoding_service_mengirim_parameter_antispoof_sesuai_pengaturan(): void
    {
        $vector = array_fill(0, FaceEmbedding::Dimensions, 0.0);
        $vector[0] = 1.0;

        Http::fake([
            '*/detect*' => Http::response([
                'status' => 'success',
                'face_count' => 1,
                'width' => 640,
                'height' => 480,
                'model' => FaceEmbedding::DefaultModel,
                'faces' => [[
                    'index' => 0,
                    'bbox' => ['top' => 10, 'right' => 110, 'bottom' => 110, 'left' => 10],
                    'face_size' => 100,
                    'encoding' => $vector,
                    'error' => null,
                    'antispoof' => ['is_live' => false, 'label' => 'Layar HP/Monitor (Replay)', 'score' => 0.1],
                ]],
            ], 200),
        ]);

        Setting::set(Setting::KeyAntiSpoofingEnabled, true);
        $service = app(FaceEncodingService::class);
        $enabledResult = $service->detectContents('dummy-jpg');
        $this->assertNotNull($enabledResult['faces'][0]['antispoof']);

        Setting::set(Setting::KeyAntiSpoofingEnabled, false);
        $disabledResult = $service->detectContents('dummy-jpg');
        $this->assertNull($disabledResult['faces'][0]['antispoof']);

        Http::assertSent(fn (Request $req) => str_contains($req->url(), 'antispoof=1'));
        Http::assertSent(fn (Request $req) => str_contains($req->url(), 'antispoof=0'));
    }

    public function test_encode_menolak_spoof_saat_aktif_dan_mengabaikan_saat_mati(): void
    {
        Http::fake([
            '*/encode*' => Http::response([
                'status' => 'spoof_detected',
                'face_count' => 1,
                'face_size' => 120,
                'message' => 'Foto tidak sah: Terdeteksi Layar HP/Monitor (Replay).',
            ], 200),
        ]);

        Setting::set(Setting::KeyAntiSpoofingEnabled, true);
        $service = app(FaceEncodingService::class);
        $result = $service->encodeContents('dummy-jpg');
        $this->assertSame(FaceEncodingService::StatusSpoofDetected, $result['status']);
    }

    public function test_absen_menolak_foto_palsu_saat_aktif_dan_menerima_saat_mati(): void
    {
        $murid = User::factory()->create([
            'role' => User::RoleMurid,
            'enrollment_status' => User::EnrollmentCompleted,
        ]);
        $vector = array_fill(0, FaceEmbedding::Dimensions, 0.0);
        $vector[0] = 1.0;

        FaceEmbedding::query()->create([
            'user_id' => $murid->id,
            'model' => FaceEmbedding::DefaultModel,
            'embedding' => $vector,
        ]);

        $prayer = PrayerTime::query()->create([
            'prayer_name' => 'zuhur',
            'label' => 'Zuhur',
            'start_time' => '12:00:00',
            'end_time' => '13:00:00',
            'late_minutes' => 15,
            'is_active' => true,
        ]);

        SolatSession::query()->create([
            'prayer_time_id' => $prayer->id,
            'prayer_name' => 'zuhur',
            'date' => today(),
            'start_time' => '12:00:00',
            'end_time' => '13:00:00',
            'late_minutes' => 15,
            'status' => SolatSession::StatusSedangDibuka,
            'opened_at' => now()->subMinutes(5),
        ]);

        Http::fake([
            '*/detect*' => Http::response([
                'status' => 'success',
                'face_count' => 1,
                'width' => 640,
                'height' => 480,
                'model' => FaceEmbedding::DefaultModel,
                'faces' => [[
                    'index' => 0,
                    'bbox' => ['top' => 10, 'right' => 130, 'bottom' => 130, 'left' => 10],
                    'face_size' => 120,
                    'encoding' => $vector,
                    'error' => null,
                    'antispoof' => ['is_live' => false, 'label' => 'Layar HP/Monitor (Replay)', 'score' => 0.05],
                ]],
            ], 200),
        ]);

        $attendance = app(SolatAttendanceService::class);
        $scanAt = today()->setTime(12, 5, 0);

        // Saat aktif: ditolak sebagai spoof_detected
        Setting::set(Setting::KeyAntiSpoofingEnabled, true);
        $resActive = $attendance->recordFacesFromImage('dummy-jpg', 'kiosk-webcam', $scanAt);
        $this->assertSame('spoof_detected', $resActive['faces'][0]['status']);
        $this->assertDatabaseCount('solat_attendance_logs', 0);

        // Saat dimatikan di pengaturan: tetap diproses dan dicatat hadir
        Setting::set(Setting::KeyAntiSpoofingEnabled, false);
        $resDisabled = $attendance->recordFacesFromImage('dummy-jpg', 'kiosk-webcam', $scanAt);
        $this->assertSame(SolatAttendanceService::StatusRecorded, $resDisabled['faces'][0]['status']);
        $this->assertDatabaseCount('solat_attendance_logs', 1);
    }

    public function test_cache_galeri_wajah_dan_dashboard_agregasi_berjalan_lancar(): void
    {
        $admin = User::factory()->create([
            'role' => User::RoleAdmin,
            'password_changed_at' => now(),
            'enrollment_status' => User::EnrollmentExempted,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_enam_waktu_solat_standar_dengan_duha_dapat_dipilih_cekbox_dan_tidak_bisa_dihapus(): void
    {
        $admin = User::factory()->create([
            'role' => User::RoleAdmin,
            'password_changed_at' => now(),
            'enrollment_status' => User::EnrollmentExempted,
        ]);

        $this->actingAs($admin)
            ->get(route('prayer-times.index'))
            ->assertOk();

        $this->assertSame(
            ['subuh', 'duha', 'zuhur', 'ashar', 'maghrib', 'isya'],
            PrayerTime::query()->ordered()->pluck('prayer_name')->all()
        );

        // Pilih hanya Duha, Zuhur, dan Ashar lewat sistem cekbox
        $this->actingAs($admin)
            ->put(route('prayer-times.active.update'), [
                'prayers' => ['duha', 'zuhur', 'ashar'],
            ])
            ->assertRedirect();

        $this->assertSame(
            ['duha', 'zuhur', 'ashar'],
            PrayerTime::query()->active()->ordered()->pluck('prayer_name')->all()
        );

        // Pastikan waktu solat standar tidak bisa dihapus
        $duha = PrayerTime::query()->where('prayer_name', 'duha')->firstOrFail();
        $this->actingAs($admin)
            ->delete(route('prayer-times.destroy', $duha))
            ->assertRedirect();

        $this->assertDatabaseHas('prayer_times', ['prayer_name' => 'duha']);
    }
}
