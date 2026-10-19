<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Fingerprint;
use App\Models\SolatSession;
use App\Models\User;
use App\Services\SolatSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FingerprintApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeDevice(string $status = Device::StatusAktif, string $deviceId = 'esp32-sensor-1'): Device
    {
        return Device::create([
            'device_id' => $deviceId,
            'name' => 'Sensor Sidik Jari Gerbang',
            'type' => Device::TypeSidikJari,
            'status' => $status,
            'location' => 'Masjid Sekolah',
        ]);
    }

    private function makeMember(string $identifier = '25006', string $name = 'Murid Uji'): User
    {
        return User::create([
            'name' => $name,
            'identifier_number' => $identifier,
            'email' => $identifier.'@smkistiqomah.sch.id',
            'role' => User::RoleMurid,
            'class_name' => 'XII TKRO 1',
            'password' => bcrypt('password123'),
        ]);
    }

    private function openSession(string $prayer = 'dzuhur'): SolatSession
    {
        $now = Carbon::now();

        return SolatSession::create([
            'prayer_name' => $prayer,
            'date' => $now->toDateString(),
            'start_time' => $now->format('H:i'),
            'end_time' => $now->copy()->addHour()->format('H:i'),
            'status' => SolatSession::StatusSedangDibuka,
            'opened_at' => $now,
            'is_test' => false,
        ]);
    }

    public function test_perangkat_baru_atau_nonaktif_ditolak_oleh_api_sidik_jari(): void
    {
        $response = $this->getJson(route('api.fingerprint.users', ['device_id' => 'alat-baru-belum-daftar']));

        $response->assertStatus(403)
            ->assertJson([
                'ok' => false,
                'status' => 'device_unregistered',
                'display_message' => 'Belum terdaftar',
            ]);

        $device = $this->makeDevice(Device::StatusNonaktif, 'alat-mati');
        $response = $this->getJson(route('api.fingerprint.users', ['device_id' => $device->device_id]));

        $response->assertStatus(403)
            ->assertJson([
                'ok' => false,
                'status' => 'device_inactive',
                'display_message' => 'Sensor nonaktif',
            ]);
    }

    public function test_api_users_dan_templates_mengembalikan_daftar_sidik_jari(): void
    {
        $device = $this->makeDevice();
        $user1 = $this->makeMember('25001', 'Siswa Satu');
        $user2 = $this->makeMember('25002', 'Siswa Dua');

        Fingerprint::create([
            'user_id' => $user1->id,
            'template' => 'TEMPLATE_BASE64_USER1',
            'finger_position' => 'Jelunjuk Kanan',
        ]);

        Fingerprint::create([
            'user_id' => $user2->id,
            'template' => 'TEMPLATE_BASE64_USER2',
            'finger_position' => 'Jelunjuk Kanan',
        ]);

        $usersResponse = $this->getJson(route('api.fingerprint.users', ['device_id' => $device->device_id]));
        $usersResponse->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    ['user_id' => '25002'],
                    ['user_id' => '25001'],
                ],
            ]);

        $templatesResponse = $this->getJson(route('api.fingerprint.templates', ['device_id' => $device->device_id]));
        $templatesResponse->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    ['user_id' => '25002', 'template' => 'TEMPLATE_BASE64_USER2'],
                    ['user_id' => '25001', 'template' => 'TEMPLATE_BASE64_USER1'],
                ],
            ]);
    }

    public function test_api_template_single_user(): void
    {
        $device = $this->makeDevice();
        $user = $this->makeMember('25006', 'Murid Khusus');

        $notFound = $this->getJson(route('api.fingerprint.template', [
            'userId' => $user->identifier_number,
            'device_id' => $device->device_id,
        ]));
        $notFound->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'message' => 'Template sidik jari tidak ditemukan.',
            ]);

        Fingerprint::create([
            'user_id' => $user->id,
            'template' => 'TEMPLATE_KHUSUS_25006',
            'finger_position' => 'Jelunjuk Kanan',
        ]);

        $found = $this->getJson(route('api.fingerprint.template', [
            'userId' => $user->identifier_number,
            'device_id' => $device->device_id,
        ]));
        $found->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'user_id' => '25006',
                    'template' => 'TEMPLATE_KHUSUS_25006',
                ],
            ]);
    }

    public function test_api_command_polling(): void
    {
        $device = $this->makeDevice();

        $defaultCommand = $this->getJson(route('api.fingerprint.command', ['device_id' => $device->device_id]));
        $defaultCommand->assertOk()->assertJson(['mode' => 'verify']);

        Cache::put('fingerprint.command.'.$device->device_id, [
            'mode' => 'enroll',
            'user_id' => '25006',
        ], 60);

        $enrollCommand = $this->getJson(route('api.fingerprint.command', ['device_id' => $device->device_id]));
        $enrollCommand->assertOk()->assertJson([
            'mode' => 'enroll',
            'user_id' => '25006',
        ]);

        $nextCommand = $this->getJson(route('api.fingerprint.command', ['device_id' => $device->device_id]));
        $nextCommand->assertOk()->assertJson(['mode' => 'verify']);
    }

    public function test_api_register_menyimpan_template_sidik_jari(): void
    {
        $device = $this->makeDevice();
        $user = $this->makeMember('25006');

        $response = $this->postJson(route('api.fingerprint.register'), [
            'device_id' => $device->device_id,
            'user_id' => $user->identifier_number,
            'template' => 'BASE64_TEMPLATE_NEW',
            'finger_position' => 'Jelunjuk Kiri',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'message' => 'Template sidik jari berhasil disimpan.',
                'data' => [
                    'user_id' => '25006',
                    'finger_position' => 'Jelunjuk Kiri',
                ],
            ]);

        $this->assertDatabaseHas('fingerprints', [
            'user_id' => $user->id,
            'template' => 'BASE64_TEMPLATE_NEW',
            'finger_position' => 'Jelunjuk Kiri',
        ]);
    }

    public function test_api_match_ketika_sidik_jari_tidak_cocok(): void
    {
        $device = $this->makeDevice();

        $response = $this->postJson(route('api.fingerprint.match'), [
            'device_id' => $device->device_id,
            'user_id' => null,
            'matched' => false,
        ]);

        $response->assertOk()
            ->assertJson([
                'ok' => false,
                'status' => 'no_match',
                'attendance_recorded' => false,
                'display_message' => 'Tidak cocok',
                'display_detail' => 'Coba lagi',
            ]);
    }

    public function test_api_match_menolak_anggota_yang_belum_didaftarkan_sidik_jari(): void
    {
        $device = $this->makeDevice();
        $user = $this->makeMember('25006', 'Anggota Tanpa Sidik Jari');
        $this->openSession();

        $response = $this->postJson(route('api.fingerprint.match'), [
            'device_id' => $device->device_id,
            'user_id' => $user->identifier_number,
            'matched' => true,
        ]);

        $response->assertOk()
            ->assertJson([
                'ok' => false,
                'status' => 'not_enrolled',
                'attendance_recorded' => false,
                'display_message' => 'Belum terdaftar',
            ]);
    }

    public function test_api_match_menolak_jika_belum_ada_sesi(): void
    {
        $device = $this->makeDevice();
        $user = $this->makeMember('25006');
        Fingerprint::create([
            'user_id' => $user->id,
            'template' => 'TEMPLATE_25006',
            'finger_position' => 'Telunjuk Kanan',
        ]);

        $response = $this->postJson(route('api.fingerprint.match'), [
            'device_id' => $device->device_id,
            'user_id' => $user->identifier_number,
            'matched' => true,
        ]);

        $response->assertOk()
            ->assertJson([
                'ok' => false,
                'status' => 'no_session',
                'attendance_recorded' => false,
                'display_message' => 'Sesi belum buka',
                'display_detail' => 'Belum dibuka',
            ]);
    }

    public function test_api_match_merekam_kehadiran_di_sesi_aktif(): void
    {
        $device = $this->makeDevice();
        $user = $this->makeMember('25006', 'Hari Poppy Latip');
        Fingerprint::create([
            'user_id' => $user->id,
            'template' => 'TEMPLATE_25006',
            'finger_position' => 'Telunjuk Kanan',
        ]);
        $session = $this->openSession();

        $response = $this->postJson(route('api.fingerprint.match'), [
            'device_id' => $device->device_id,
            'user_id' => $user->identifier_number,
            'matched' => true,
        ]);

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'attendance_recorded' => true,
                'user_id' => '25006',
                'name' => 'Hari Poppy Latip',
                'display_message' => 'Absen tersimpan',
            ]);

        $this->assertStringContainsString('via sidik jari', (string) $response->json('message'));

        $this->assertDatabaseHas('solat_attendance_logs', [
            'user_id' => $user->id,
            'solat_session_id' => $session->id,
            'device_id' => $device->device_id,
        ]);

        // Tap kedua di sesi yang sama harus ditolak (duplicate)
        $duplicate = $this->postJson(route('api.fingerprint.match'), [
            'device_id' => $device->device_id,
            'user_id' => $user->identifier_number,
            'matched' => true,
        ]);

        $duplicate->assertOk()
            ->assertJson([
                'ok' => false,
                'status' => 'duplicate',
                'attendance_recorded' => false,
                'display_message' => 'Sudah absen',
            ]);
    }

    public function test_api_match_berfungsi_pada_mode_uji_coba(): void
    {
        $device = $this->makeDevice();
        $user = $this->makeMember('25006', 'Haris Tester');
        Fingerprint::create([
            'user_id' => $user->id,
            'template' => 'TEMPLATE_25006',
            'finger_position' => 'Telunjuk Kanan',
        ]);

        // Nyalakan mode uji coba
        app(SolatSessionService::class)->startTestSession();

        $response = $this->postJson(route('api.fingerprint.match'), [
            'device_id' => $device->device_id,
            'user_id' => $user->identifier_number,
            'matched' => true,
        ]);

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'attendance_recorded' => true,
                'attendance_type' => 'test',
                'user_id' => '25006',
            ]);

        $this->assertStringContainsString('absen via sidik jari', (string) $response->json('message'));
    }
}
