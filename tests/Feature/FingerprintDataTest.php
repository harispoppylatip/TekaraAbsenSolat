<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Fingerprint;
use App\Models\SolatAttendanceLog;
use App\Models\SolatSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FingerprintDataTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::create([
            'name' => 'Administrator',
            'identifier_number' => 'ADM01',
            'email' => 'admin@smkistiqomah.sch.id',
            'role' => User::RoleAdmin,
            'password' => bcrypt('password123'),
        ]);

        $user->forceFill(['password_changed_at' => now()])->save();

        return $user->refresh();
    }

    private function makeMember(string $identifier = '10001', string $name = 'Hari Poppy Latip'): User
    {
        return User::create([
            'name' => $name,
            'identifier_number' => $identifier,
            'email' => $identifier.'@smkistiqomah.sch.id',
            'role' => User::RoleMurid,
            'class_name' => 'XII RPL 1',
            'password' => bcrypt('password123'),
        ]);
    }

    public function test_admin_dapat_membuka_halaman_data_sidik_jari_dan_mengirim_perintah_enroll(): void
    {
        $admin = $this->makeAdmin();
        $member = $this->makeMember('10001', 'Hari Poppy Latip');

        $device = Device::create([
            'device_id' => 'esp32-002',
            'name' => 'Sensor Sidik Jari Utama',
            'type' => Device::TypeSidikJari,
            'status' => Device::StatusAktif,
            'location' => 'Masjid',
        ]);

        $page = $this->actingAs($admin)->get(route('fingerprint.index'));
        $page->assertOk()
            ->assertSee('Data Sidik Jari')
            ->assertSee('Pilih dan daftarkan anggota')
            ->assertSee('Hari Poppy Latip');

        $enroll = $this->actingAs($admin)->post(route('fingerprint.enroll'), [
            'user_id' => $member->id,
            'finger_position' => 'Telunjuk Kanan',
        ]);

        $enroll->assertRedirect();

        // Pastikan sensor ESP32 menerima perintah enroll dari endpoint /api/fingerprint/command
        $cmdResponse = $this->getJson(route('api.fingerprint.command', [
            'device_id' => $device->device_id,
        ]));

        $cmdResponse->assertOk()
            ->assertJson([
                'mode' => 'enroll',
                'user_id' => '10001',
                'finger_position' => 'Telunjuk Kanan',
            ]);
    }

    public function test_admin_dapat_merekam_ulang_dan_menghapus_sidik_jari_anggota(): void
    {
        $admin = $this->makeAdmin();
        $member = $this->makeMember('10001', 'Hari Poppy Latip');

        Fingerprint::create([
            'user_id' => $member->id,
            'template' => 'OLD_TEMPLATE_DATA',
            'finger_position' => 'Telunjuk Kanan',
        ]);

        $replace = $this->actingAs($admin)->post(route('fingerprint.replace', $member));
        $replace->assertRedirect();

        $this->assertDatabaseMissing('fingerprints', [
            'user_id' => $member->id,
            'template' => 'OLD_TEMPLATE_DATA',
        ]);

        $this->assertNotNull(Cache::get('fingerprint.pending_enroll'));

        // Simpan kembali lalu uji hapus
        Fingerprint::create([
            'user_id' => $member->id,
            'template' => 'NEW_TEMPLATE_DATA',
            'finger_position' => 'Jempol Kanan',
        ]);

        $delete = $this->actingAs($admin)->delete(route('fingerprint.member.destroy', $member));
        $delete->assertRedirect();

        $this->assertDatabaseCount('fingerprints', 0);
    }

    public function test_log_absen_menampilkan_tulisan_absen_via_sidik_jari(): void
    {
        $admin = $this->makeAdmin();
        $member = $this->makeMember('10001', 'Hari Poppy Latip');

        Fingerprint::create([
            'user_id' => $member->id,
            'template' => 'TEMPLATE_HARIS',
            'finger_position' => 'Telunjuk Kanan',
        ]);

        $now = Carbon::now();
        $session = SolatSession::create([
            'prayer_name' => 'zuhur',
            'date' => $now->toDateString(),
            'start_time' => '12:00',
            'end_time' => '12:45',
            'status' => SolatSession::StatusSedangDibuka,
            'opened_at' => $now,
            'is_test' => false,
        ]);

        $log = SolatAttendanceLog::create([
            'user_id' => $member->id,
            'solat_session_id' => $session->id,
            'scanned_at' => Carbon::parse($now->toDateString().' 12:05:14'),
            'status' => SolatAttendanceLog::StatusHadir,
            'device_id' => 'esp32-002',
            'proof_photo_path' => null,
        ]);

        $this->assertTrue($log->isFingerprint());
        $this->assertSame('Hari Poppy Latip absen via sidik jari pukul 12:05:14', $log->summaryLine());

        $fpPage = $this->actingAs($admin)->get(route('fingerprint.index'));
        $fpPage->assertOk()
            ->assertSee('Hari Poppy Latip absen via sidik jari');

        $dashboard = $this->actingAs($admin)->get(route('dashboard'));
        $dashboard->assertOk()
            ->assertSee('Hari Poppy Latip absen via sidik jari pukul 12:05:14');

        $sessionPage = $this->actingAs($admin)->get(route('sessions.index', [
            'date' => $now->toDateString(),
            'session' => $session->id,
        ]));
        $sessionPage->assertOk()
            ->assertSee('Hari Poppy Latip absen via sidik jari pukul 12:05:14')
            ->assertSee('Via sidik jari (esp32-002)');
    }
}
