<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\FaceEmbedding;
use App\Models\PrayerTime;
use App\Models\SolatAttendanceLog;
use App\Models\SolatSession;
use App\Models\User;
use App\Services\SystemBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemBackupTest extends TestCase
{
    use RefreshDatabase;

    private function createVerifiedUser(array $overrides = []): User
    {
        $user = User::factory()->create($overrides);
        $user->forceFill(['password_changed_at' => now()])->save();

        return $user;
    }

    public function test_only_admin_can_download_and_restore_backup(): void
    {
        $murid = $this->createVerifiedUser(['role' => User::RoleMurid]);
        $petugas = $this->createVerifiedUser(['role' => User::RolePetugas]);

        $this->actingAs($murid)
            ->get(route('settings.backup.download'))
            ->assertRedirect(route('murid.riwayat'));

        $this->actingAs($petugas)
            ->get(route('settings.backup.download'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($murid)
            ->post(route('settings.backup.restore'))
            ->assertRedirect(route('murid.riwayat'));
    }

    public function test_admin_can_download_full_backup_zip_and_restore_everything(): void
    {
        Storage::fake('local');

        $admin = $this->createVerifiedUser([
            'name' => 'Admin Sekolah',
            'identifier_number' => '999000111',
            'email' => 'admin@smkim4.sch.id',
            'role' => User::RoleAdmin,
        ]);

        $murid = $this->createVerifiedUser([
            'name' => 'Ahmad Fauzi',
            'identifier_number' => '0081234567',
            'email' => null,
            'role' => User::RoleMurid,
            'enrollment_status' => User::EnrollmentCompleted,
        ]);

        PrayerTime::query()->create([
            'prayer_name' => 'zuhur',
            'label' => 'Zuhur',
            'start_time' => '12:00:00',
            'end_time' => '13:00:00',
            'late_minutes' => 15,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Device::query()->create([
            'device_id' => 'CAM-UTAMA-01',
            'name' => 'Kamera Musholla',
            'type' => Device::TypeGerbang,
            'status' => Device::StatusAktif,
            'location' => 'Musholla Utama',
        ]);

        $facePhotoPath = 'face-photos/'.$murid->id.'/wajah-1.jpg';
        $proofPhotoPath = 'bukti-absen/2026/10/bukti-zuhur.jpg';
        Storage::disk('local')->put($facePhotoPath, 'JPEG_DATA_WAJAH_MURID');
        Storage::disk('local')->put($proofPhotoPath, 'JPEG_DATA_BUKTI_SOLAT');

        FaceEmbedding::query()->create([
            'user_id' => $murid->id,
            'embedding' => array_fill(0, 8, 0.125),
            'photo_path' => $facePhotoPath,
            'pose_key' => 'lurus',
            'pose_label' => 'Menghadap Lurus',
            'face_confidence' => 0.98,
            'source' => 'self_enrollment',
        ]);

        $session = SolatSession::query()->create([
            'prayer_name' => 'zuhur',
            'date' => '2026-10-17',
            'start_time' => '12:00:00',
            'end_time' => '13:00:00',
            'status' => SolatSession::StatusSedangDibuka,
            'is_test' => false,
            'opened_by' => $admin->id,
            'opened_at' => now(),
        ]);

        SolatAttendanceLog::query()->create([
            'solat_session_id' => $session->id,
            'user_id' => $murid->id,
            'device_id' => 'CAM-UTAMA-01',
            'scanned_at' => now(),
            'status' => SolatAttendanceLog::StatusHadir,
            'match_distance' => 0.28,
            'proof_photo_path' => $proofPhotoPath,
        ]);

        // 1. Unduh backup .zip
        $downloadResponse = $this->actingAs($admin)
            ->get(route('settings.backup.download'));

        $downloadResponse->assertOk();
        $downloadResponse->assertHeader('Content-Type', 'application/zip');

        $zipBytes = $downloadResponse->getContent();
        $this->assertNotEmpty($zipBytes);
        $this->assertSame("PK\x03\x04", substr($zipBytes, 0, 4));

        // 2. Hapus bersih seluruh data operasional dan berkas foto
        $this->actingAs($admin)
            ->delete(route('settings.clear-all'), ['confirm_text' => 'HAPUS SEMUA'])
            ->assertRedirect();

        $this->assertDatabaseMissing('users', ['identifier_number' => '0081234567']);
        $this->assertSame(0, FaceEmbedding::query()->count());
        $this->assertSame(0, SolatAttendanceLog::query()->count());
        Storage::disk('local')->assertMissing($facePhotoPath);
        Storage::disk('local')->assertMissing($proofPhotoPath);

        // 3. Unggah berkas .zip untuk Restore
        $uploadedZip = UploadedFile::fake()->createWithContent('backup-absen-solat.zip', $zipBytes);

        $restoreResponse = $this->actingAs($admin)
            ->post(route('settings.backup.restore'), [
                'backup_file' => $uploadedZip,
                'confirm_restore' => '1',
            ]);

        $restoreResponse->assertRedirect(route('settings.index'));
        $restoreResponse->assertSessionHas('status');

        // 4. Pastikan seluruh database dan berkas fisik foto anak-anak kembali 100%
        $this->assertDatabaseHas('users', [
            'name' => 'Ahmad Fauzi',
            'identifier_number' => '0081234567',
            'role' => User::RoleMurid,
        ]);
        $this->assertSame(1, FaceEmbedding::query()->count());
        $this->assertSame(1, SolatSession::query()->count());
        $this->assertSame(1, SolatAttendanceLog::query()->count());
        $this->assertSame(1, Device::query()->count());

        Storage::disk('local')->assertExists($facePhotoPath);
        Storage::disk('local')->assertExists($proofPhotoPath);
        $this->assertSame('JPEG_DATA_WAJAH_MURID', Storage::disk('local')->get($facePhotoPath));
        $this->assertSame('JPEG_DATA_BUKTI_SOLAT', Storage::disk('local')->get($proofPhotoPath));
    }

    public function test_restore_rejects_invalid_zip_without_deleting_existing_data(): void
    {
        Storage::fake('local');

        $admin = $this->createVerifiedUser(['role' => User::RoleAdmin]);
        $this->createVerifiedUser([
            'name' => 'Siswa Aman',
            'identifier_number' => '0099887766',
            'role' => User::RoleMurid,
        ]);

        /** @var SystemBackupService $service */
        $service = app(SystemBackupService::class);
        $invalidZipBytes = $service->buildZip([
            'hello.txt' => 'Bukan backup absen solat',
        ]);

        $uploadedZip = UploadedFile::fake()->createWithContent('bukan-backup.zip', $invalidZipBytes);

        $response = $this->actingAs($admin)
            ->from(route('settings.index'))
            ->post(route('settings.backup.restore'), [
                'backup_file' => $uploadedZip,
                'confirm_restore' => '1',
            ]);

        $response->assertRedirect(route('settings.index'));
        $response->assertSessionHas('error');

        // Pastikan data siswa tidak hilang
        $this->assertDatabaseHas('users', [
            'name' => 'Siswa Aman',
            'identifier_number' => '0099887766',
        ]);
    }
}
