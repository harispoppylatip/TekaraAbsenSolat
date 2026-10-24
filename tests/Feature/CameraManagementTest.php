<?php

namespace Tests\Feature;

use App\Models\Camera;
use App\Models\User;
use App\Services\SystemBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CameraManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_hanya_admin_yang_dapat_mengakses_halaman_kamera(): void
    {
        $admin = $this->makeUser(User::RoleAdmin);
        $petugas = $this->makeUser(User::RolePetugas);
        $murid = $this->makeUser(User::RoleMurid);

        $this->get(route('cameras.index'))->assertRedirect(route('login'));
        $this->actingAs($murid)->get(route('cameras.index'))->assertRedirect(route('murid.riwayat'));
        $this->actingAs($petugas)->get(route('cameras.index'))->assertRedirect(route('dashboard'));

        $this->actingAs($admin)
            ->get(route('cameras.index'))
            ->assertOk()
            ->assertSee('Kamera CCTV')
            ->assertSee('Web Stream (fMP4/HTTP)')
            ->assertSee('RTSP CCTV');
    }

    public function test_admin_dapat_menambahkan_kamera_web_stream_dan_rtsp(): void
    {
        $admin = $this->makeUser(User::RoleAdmin);

        $samarindaUrl = 'https://vmssamarinda.iconpln.co.id:8443/mt/api/rest/v1/media?format=fmp4&quality=high&t=live';

        // 1. Simpan Web Stream
        $this->actingAs($admin)->post(route('cameras.store'), [
            'name' => 'CCTV Gerbang Samarinda',
            'stream_type' => Camera::TypeWebStream,
            'stream_url' => $samarindaUrl,
            'location' => 'Pintu Gerbang Depan',
            'is_active' => '1',
            'notes' => 'Siaran fMP4 VMS Samarinda',
        ])->assertRedirect(route('cameras.index', ['kamera' => 1]));

        $this->assertDatabaseHas('cameras', [
            'name' => 'CCTV Gerbang Samarinda',
            'stream_type' => Camera::TypeWebStream,
            'is_active' => true,
        ]);

        // 2. Simpan RTSP CCTV
        $this->actingAs($admin)->post(route('cameras.store'), [
            'name' => 'CCTV Musala Dalam',
            'stream_type' => Camera::TypeRtsp,
            'stream_url' => 'rtsp://admin:pass@192.168.1.100:554/Streaming/Channels/101',
            'snapshot_url' => 'http://192.168.1.100/ISAPI/Streaming/channels/101/picture',
            'location' => 'Musala Lantai 1',
            'is_active' => '1',
        ])->assertRedirect(route('cameras.index', ['kamera' => 2]));

        $this->assertDatabaseHas('cameras', [
            'name' => 'CCTV Musala Dalam',
            'stream_type' => Camera::TypeRtsp,
            'is_active' => true,
        ]);
    }

    public function test_validasi_input_saat_menambahkan_kamera(): void
    {
        $admin = $this->makeUser(User::RoleAdmin);

        $this->actingAs($admin)->post(route('cameras.store'), [
            'name' => '',
            'stream_type' => 'jenis_tidak_valid',
            'stream_url' => '',
        ])->assertSessionHasErrors(['name', 'stream_type', 'stream_url']);
    }

    public function test_admin_dapat_mengubah_data_kamera(): void
    {
        $admin = $this->makeUser(User::RoleAdmin);

        $camera = Camera::create([
            'name' => 'CCTV Awal',
            'stream_type' => Camera::TypeWebStream,
            'stream_url' => 'https://example.com/stream.mp4',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('cameras.edit', $camera))
            ->assertOk()
            ->assertSee('Ubah data kamera: CCTV Awal');

        $this->actingAs($admin)->put(route('cameras.update', $camera), [
            'name' => 'CCTV Diperbarui',
            'stream_type' => Camera::TypeRtsp,
            'stream_url' => 'rtsp://192.168.1.200:554/ch1',
            'location' => 'Musala Lantai 2',
            'is_active' => '1',
        ])->assertRedirect(route('cameras.index', ['kamera' => $camera->id]));

        $camera->refresh();
        $this->assertSame('CCTV Diperbarui', $camera->name);
        $this->assertSame(Camera::TypeRtsp, $camera->stream_type);
        $this->assertSame('rtsp://192.168.1.200:554/ch1', $camera->stream_url);
        $this->assertSame('Musala Lantai 2', $camera->location);
    }

    public function test_admin_dapat_mengubah_status_aktif_dan_menghapus_kamera(): void
    {
        $admin = $this->makeUser(User::RoleAdmin);

        $camera = Camera::create([
            'name' => 'CCTV Sakelar',
            'stream_type' => Camera::TypeWebStream,
            'stream_url' => 'https://example.com/test.mp4',
            'is_active' => true,
        ]);

        // Toggle nonaktif
        $this->actingAs($admin)->put(route('cameras.status', $camera))
            ->assertRedirect();
        $this->assertFalse($camera->fresh()->is_active);

        // Toggle aktif kembali
        $this->actingAs($admin)->put(route('cameras.status', $camera))
            ->assertRedirect();
        $this->assertTrue($camera->fresh()->is_active);

        // Hapus kamera
        $this->actingAs($admin)->delete(route('cameras.destroy', $camera))
            ->assertRedirect(route('cameras.index'));
        $this->assertDatabaseMissing('cameras', ['id' => $camera->id]);
    }

    public function test_halaman_kamera_menampilkan_pemantau_cctv_dan_tombol_pemindai_wajah(): void
    {
        $admin = $this->makeUser(User::RoleAdmin);

        $camera = Camera::create([
            'name' => 'CCTV Gerbang Utama',
            'stream_type' => Camera::TypeWebStream,
            'stream_url' => 'https://example.com/live.mp4',
            'location' => 'Gerbang Depan',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('cameras.index'));
        $response->assertOk();
        $response->assertSee('Pemantau CCTV: CCTV Gerbang Utama');
        $response->assertSee('Aktifkan pemindai wajah');
        $response->assertSee('Muat ulang siaran');
        $response->assertSee('Log pemindaian CCTV');
    }

    public function test_kamera_cctv_tidak_digabung_ke_halaman_absen_wajah(): void
    {
        $admin = $this->makeUser(User::RoleAdmin);

        Camera::create([
            'name' => 'CCTV Khusus',
            'stream_type' => Camera::TypeWebStream,
            'stream_url' => 'https://example.com/cctv.mp4',
            'is_active' => true,
        ]);

        // Halaman absen wajah tetap bersih untuk webcam peramban
        $response = $this->actingAs($admin)->get(route('attendance.wajah'));
        $response->assertOk();
        $response->assertSee('Kamera depan');
        $response->assertSee('Kamera belakang');
        $response->assertDontSee('data-cctv-group', false);
    }

    public function test_tabel_kamera_terdaftar_pada_layanan_cadangan_sistem(): void
    {
        $this->assertContains('cameras', SystemBackupService::Tables);
    }

    public function test_uji_koneksi_api_merespons_format_json(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://example.com/sample' => \Illuminate\Support\Facades\Http::response('OK', 200),
        ]);

        $admin = $this->makeUser(User::RoleAdmin);

        $response = $this->actingAs($admin)->postJson(route('cameras.test'), [
            'stream_type' => Camera::TypeWebStream,
            'stream_url' => 'https://example.com/sample',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['success', 'message']);
    }

    public function test_endpoint_logs_kamera_mengembalikan_json(): void
    {
        $admin = $this->makeUser(User::RoleAdmin);
        $camera = Camera::create([
            'name' => 'CCTV Pantau',
            'stream_type' => Camera::TypeWebStream,
            'stream_url' => 'https://example.com/stream.mp4',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->getJson(route('cameras.logs', $camera));
        $response->assertOk()
            ->assertJsonStructure(['logs', 'worker_active']);
    }

    public function test_perintah_artisan_cctv_auto_scan_dapat_dieksekusi_once(): void
    {
        $this->artisan('cctv:auto-scan', ['--once' => true])
            ->assertExitCode(0);
    }
}
