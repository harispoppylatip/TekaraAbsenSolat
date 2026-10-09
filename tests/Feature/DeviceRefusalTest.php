<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\FaceEmbedding;
use App\Models\SolatAttendanceLog;
use App\Models\User;
use App\Services\SolatSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DeviceRefusalTest extends TestCase
{
    use RefreshDatabase;

    private function makeDevice(string $status, string $deviceId = 'kamera-gerbang-1'): Device
    {
        return Device::create([
            'device_id' => $deviceId,
            'name' => 'Kamera Gerbang',
            'type' => Device::TypeGerbang,
            'status' => $status,
        ]);
    }

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
     * Kirim potret dari alat tanpa header Accept, sama seperti alat sungguhan
     * yang hanya peduli pada isi balasan.
     */
    private function scan(array $payload = [], array $headers = []): TestResponse
    {
        return $this->withHeaders($headers)->post(
            route('api.solat.scan'),
            array_merge(['photo' => UploadedFile::fake()->image('absen.jpg')], $payload),
        );
    }

    public function test_perangkat_baru_tercatat_belum_didaftarkan_dan_ditolak(): void
    {
        $this->seedPrayerTimes();
        $this->fakeFaceService();

        $this->scan(['device_id' => 'kamera-gerbang-1'])
            ->assertStatus(403)
            ->assertJson([
                'ok' => false,
                'blocked' => true,
                'status' => 'device_unregistered',
                'display_message' => 'Belum terdaftar',
                'display_detail' => 'Lapor operator',
            ]);

        $device = Device::query()->where('device_id', 'kamera-gerbang-1')->firstOrFail();

        $this->assertSame(Device::StatusBelumDidaftarkan, $device->status);
        $this->assertSame(0, SolatAttendanceLog::query()->count());
    }

    public function test_alat_tanpa_kode_perangkat_ditolak(): void
    {
        $this->seedPrayerTimes();
        $this->fakeFaceService();

        $this->scan()
            ->assertStatus(422)
            ->assertJson([
                'blocked' => true,
                'status' => 'device_id_missing',
                'display_message' => 'Kode alat kosong',
            ]);

        $this->assertSame(0, Device::query()->count());
    }

    public function test_kode_perangkat_boleh_dikirim_lewat_header(): void
    {
        $this->seedPrayerTimes();
        $this->makeDevice(Device::StatusNonaktif);
        $this->fakeFaceService();

        $this->scan([], ['X-Device-Id' => 'kamera-gerbang-1'])
            ->assertStatus(403)
            ->assertJson(['status' => 'device_inactive', 'display_message' => 'Sensor nonaktif']);
    }

    public function test_perangkat_yang_dimatikan_ditolak(): void
    {
        $this->seedPrayerTimes();
        $this->makeDevice(Device::StatusNonaktif);
        $this->fakeFaceService();

        $this->scan(['device_id' => 'kamera-gerbang-1'])
            ->assertStatus(403)
            ->assertJson([
                'ok' => false,
                'blocked' => true,
                'status' => 'device_inactive',
                'display_message' => 'Sensor nonaktif',
                'display_detail' => 'Lapor operator',
            ]);

        $this->assertSame(0, SolatAttendanceLog::query()->count());
    }

    public function test_perangkat_terdaftar_dan_aktif_boleh_mengirim_absen(): void
    {
        $this->seedPrayerTimes();
        $this->makeDevice(Device::StatusAktif);
        $this->enrolledMember();

        $admin = $this->makeUser(User::RoleAdmin);
        $session = $this->makeSession('zuhur');
        $this->travelTo(Carbon::parse('today 12:05'));
        app(SolatSessionService::class)->open($session, $admin);

        $this->fakeFaceService();

        $this->scan(['device_id' => 'kamera-gerbang-1'])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'blocked' => false,
                'status' => 'recorded',
                'display_message' => 'Absen tersimpan',
            ]);

        $this->assertSame(1, SolatAttendanceLog::query()->count());
        $this->assertNotNull(Device::query()->firstOrFail()->last_seen_at);
    }

    public function test_alat_tanpa_foto_ditolak(): void
    {
        $this->seedPrayerTimes();
        $this->makeDevice(Device::StatusAktif);
        $this->fakeFaceService();

        $this->post(route('api.solat.scan'), ['device_id' => 'kamera-gerbang-1'])
            ->assertStatus(422)
            ->assertJson([
                'blocked' => true,
                'status' => 'invalid_request',
                'display_message' => 'Data alat kurang',
                'display_detail' => 'Lapor operator',
            ]);
    }

    public function test_semua_balasan_muat_di_layar_enam_belas_kolom(): void
    {
        $this->seedPrayerTimes();
        $this->makeDevice(Device::StatusNonaktif);
        $this->fakeFaceService();

        $cases = [
            $this->scan(),
            $this->scan(['device_id' => 'kamera-gerbang-1']),
            $this->scan(['device_id' => 'alat-asing']),
            $this->post(route('api.solat.scan'), ['device_id' => 'kamera-gerbang-1']),
        ];

        foreach ($cases as $response) {
            $payload = $response->json();

            $this->assertLessThanOrEqual(16, mb_strlen((string) $payload['display_message']));
            $this->assertLessThanOrEqual(16, mb_strlen((string) $payload['display_detail']));
            $this->assertSame('Lapor operator', $payload['display_detail']);
            $this->assertTrue($payload['blocked']);
        }
    }

    public function test_keadaan_sesi_untuk_alat(): void
    {
        $this->seedPrayerTimes();
        $this->makeDevice(Device::StatusAktif);

        $this->getJson(route('api.solat.session', ['device_id' => 'kamera-gerbang-1']))
            ->assertOk()
            ->assertJson([
                'ok' => false,
                'status' => 'no_session',
                'display_message' => 'Sesi belum buka',
                'display_detail' => 'Lapor operator',
            ]);

        $admin = $this->makeUser(User::RoleAdmin);
        $session = $this->makeSession('zuhur');
        $this->travelTo(Carbon::parse('today 12:05'));
        app(SolatSessionService::class)->open($session, $admin);

        $this->getJson(route('api.solat.session', ['device_id' => 'kamera-gerbang-1']))
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'status' => 'open',
                'display_message' => 'Zuhur',
                'display_detail' => 'Absen siap',
                'session' => [
                    'prayer_name' => 'zuhur',
                    'start_time' => '12:00',
                    'end_time' => '12:45',
                ],
            ]);
    }

    public function test_pendaftaran_dan_penyalakan_perangkat_lewat_halaman(): void
    {
        $this->seedPrayerTimes();
        $admin = $this->makeUser(User::RoleAdmin);
        $device = $this->makeDevice(Device::StatusBelumDidaftarkan);
        $this->fakeFaceService();

        // Belum didaftarkan: alat ditolak.
        $this->scan(['device_id' => 'kamera-gerbang-1'])->assertStatus(403);

        $this->actingAs($admin)
            ->put(route('devices.register', $device))
            ->assertRedirect();

        $this->assertSame(Device::StatusNonaktif, $device->refresh()->status);

        // Sudah didaftarkan tetapi belum dinyalakan.
        $this->scan(['device_id' => 'kamera-gerbang-1'])
            ->assertStatus(403)
            ->assertJson(['display_message' => 'Sensor nonaktif']);

        $this->actingAs($admin)
            ->put(route('devices.status', $device), ['active' => 1])
            ->assertRedirect();

        $this->assertSame(Device::StatusAktif, $device->refresh()->status);

        // Sudah didaftarkan dan dinyalakan, tetapi belum ada sesi yang dibuka.
        $this->scan(['device_id' => 'kamera-gerbang-1'])
            ->assertOk()
            ->assertJson(['ok' => false, 'status' => 'no_session', 'display_message' => 'Sesi belum buka']);
    }

    public function test_perangkat_yang_belum_didaftarkan_muncul_di_halaman_perangkat(): void
    {
        $this->seedPrayerTimes();
        $admin = $this->makeUser(User::RoleAdmin);
        $this->fakeFaceService();

        $this->scan(['device_id' => 'kamera-gerbang-1'])->assertStatus(403);

        $this->actingAs($admin)
            ->get(route('devices.index'))
            ->assertOk()
            ->assertSee('Perangkat baru')
            ->assertSee('kamera-gerbang-1');
    }
}
