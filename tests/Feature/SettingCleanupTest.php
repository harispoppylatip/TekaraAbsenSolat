<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\FaceEmbedding;
use App\Models\FacePhotoUpload;
use App\Models\PrayerTime;
use App\Models\SolatAttendanceLog;
use App\Models\SolatSession;
use App\Models\User;
use App\Services\SolatProofService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_hanya_admin_yang_boleh_membuka_dan_menjalankan_pengaturan(): void
    {
        $admin = $this->makeUser(User::RoleAdmin);
        $petugas = $this->makeUser(User::RolePetugas);
        $murid = $this->makeUser(User::RoleMurid);

        $this->actingAs($petugas)->get(route('settings.index'))->assertRedirect(route('dashboard'));
        $this->actingAs($murid)->get(route('settings.index'))->assertRedirect(route('murid.riwayat'));
        $this->actingAs($petugas)->delete(route('settings.clear-attendance'))->assertRedirect(route('dashboard'));

        $this->actingAs($admin)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Pengaturan, Cadangan & Pembersihan Data')
            ->assertSee('Hapus Seluruh Riwayat Absen')
            ->assertSee('Hapus Seluruh Data (Reset Total Sistem)');
    }

    public function test_hapus_seluruh_riwayat_absen_menghapus_catatan_dan_foto_bukti_tanpa_menghapus_sesi(): void
    {
        Storage::fake('local');

        $admin = $this->makeUser(User::RoleAdmin);
        $murid = $this->makeUser(User::RoleMurid);

        $session = SolatSession::create([
            'prayer_name' => 'Dzuhur',
            'date' => today()->toDateString(),
            'start_time' => '12:00',
            'end_time' => '13:00',
            'status' => SolatSession::StatusSudahDitutup,
        ]);

        $proofPath = SolatProofService::Folder.'/2026/10/bukti1.jpg';
        Storage::disk('local')->put($proofPath, 'jpeg-bytes');

        SolatAttendanceLog::create([
            'solat_session_id' => $session->id,
            'user_id' => $murid->id,
            'scanned_at' => now(),
            'status' => SolatAttendanceLog::StatusHadir,
            'proof_photo_path' => $proofPath,
        ]);

        $this->actingAs($admin)
            ->delete(route('settings.clear-attendance'))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(0, SolatAttendanceLog::query()->count());
        $this->assertFalse(Storage::disk('local')->exists($proofPath));
        $this->assertSame(1, SolatSession::query()->count());
    }

    public function test_hapus_seluruh_data_wajah_menghapus_foto_dan_mereset_status_pendaftaran(): void
    {
        Storage::fake('local');

        $admin = $this->makeUser(User::RoleAdmin);
        $murid = $this->makeUser(User::RoleMurid, ['enrollment_status' => User::EnrollmentCompleted, 'enrolled_at' => now()]);

        $photoPath = 'face-photos/'.$murid->id.'/wajah1.jpg';
        Storage::disk('local')->put($photoPath, 'face-bytes');

        FaceEmbedding::create([
            'user_id' => $murid->id,
            'label' => 'wajah1.jpg',
            'photo_path' => $photoPath,
            'model' => FaceEmbedding::DefaultModel,
            'embedding' => array_fill(0, FaceEmbedding::Dimensions, 0.01),
        ]);

        $this->actingAs($admin)
            ->delete(route('settings.clear-faces'))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(0, FaceEmbedding::query()->count());
        $this->assertFalse(Storage::disk('local')->exists($photoPath));
        $this->assertSame(User::EnrollmentNotStarted, $murid->refresh()->enrollment_status);
        $this->assertNull($murid->enrolled_at);
    }

    public function test_reset_total_sistem_wajib_ketik_hapus_semua_dan_menjaga_akun_admin(): void
    {
        Storage::fake('local');

        $admin = $this->makeUser(User::RoleAdmin);
        $petugas = $this->makeUser(User::RolePetugas);
        $murid = $this->makeUser(User::RoleMurid);

        PrayerTime::create([
            'prayer_name' => 'Ashar',
            'label' => 'Ashar',
            'start_time' => '15:15',
            'end_time' => '16:00',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $session = SolatSession::create([
            'prayer_name' => 'Ashar',
            'date' => today()->toDateString(),
            'start_time' => '15:15',
            'end_time' => '16:00',
            'status' => SolatSession::StatusSudahDitutup,
        ]);

        SolatAttendanceLog::create([
            'solat_session_id' => $session->id,
            'user_id' => $murid->id,
            'scanned_at' => now(),
            'status' => SolatAttendanceLog::StatusHadir,
        ]);

        Device::create([
            'device_id' => 'kamera-gerbang',
            'name' => 'Kamera Gerbang',
            'status' => Device::StatusAktif,
        ]);

        // Gagal bila teks konfirmasi salah
        $this->actingAs($admin)
            ->from(route('settings.index'))
            ->delete(route('settings.clear-all'), ['confirm_text' => 'SALAH'])
            ->assertRedirect(route('settings.index'))
            ->assertSessionHasErrors('confirm_text');

        $this->assertSame(1, SolatSession::query()->count());

        // Berhasil bila mengetik HAPUS SEMUA
        $this->actingAs($admin)
            ->delete(route('settings.clear-all'), ['confirm_text' => 'HAPUS SEMUA'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(0, SolatAttendanceLog::query()->count());
        $this->assertSame(0, SolatSession::query()->count());
        $this->assertSame(0, FaceEmbedding::query()->count());
        $this->assertSame(0, FacePhotoUpload::query()->count());
        $this->assertSame(0, Device::query()->count());
        $this->assertDatabaseMissing('users', ['id' => $petugas->id]);
        $this->assertDatabaseMissing('users', ['id' => $murid->id]);

        // Akun admin dan jadwal waktu solat tetap utuh
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => User::RoleAdmin]);
        $this->assertSame(1, PrayerTime::query()->count());
    }
}
