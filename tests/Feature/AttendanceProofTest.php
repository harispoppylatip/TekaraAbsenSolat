<?php

namespace Tests\Feature;

use App\Models\FaceEmbedding;
use App\Models\SolatAttendanceLog;
use App\Models\SolatSession;
use App\Models\User;
use App\Services\FaceEncodingService;
use App\Services\SolatAttendanceService;
use App\Services\SolatProofService;
use App\Services\SolatSessionService;
use Carbon\CarbonInterface;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Foto bukti absen wajah.
 *
 * Mesin wajah mengirim balik potret seluruh bingkai yang sudah diperkecil, lalu
 * aplikasi menyimpannya sebagai bukti dan menampilkannya di halaman riwayat.
 * Fokus pengujian ini: buktinya benar-benar tersimpan, tidak pernah menimpa
 * bukti lama, tidak bisa dilihat orang lain, dan tidak pernah menggagalkan
 * absennya sendiri.
 */
class AttendanceProofTest extends TestCase
{
    use RefreshDatabase;

    /** Isi berkas bukti yang dikirim mesin wajah pada pengujian. */
    private const ProofBytes = "\xFF\xD8\xFF\xE0potret uji";

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(SolatProofService::Disk);
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

        $this->assertTrue($result['ok'], 'Sesi zuhur gagal dibuka.');

        return $session->refresh();
    }

    /**
     * Pindai satu wajah dari halaman absen wajah seperti petugas kamera.
     */
    private function scanFromPage(): TestResponse
    {
        $petugas = $this->makeUser(User::RolePetugas, ['can_scan_face' => true]);

        return $this->actingAs($petugas)->post(route('attendance.wajah.scan'), [
            'photo' => UploadedFile::fake()->image('absen.jpg'),
        ]);
    }

    /**
     * Catatan absen yang sudah punya berkas bukti, tanpa lewat kamera.
     */
    private function proofLog(SolatSession $session, ?CarbonInterface $at = null): SolatAttendanceLog
    {
        $moment = $at !== null ? Carbon::parse($at) : Carbon::now();
        $proofs = app(SolatProofService::class);
        $path = $proofs->store(self::ProofBytes, $session, $moment);

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

    private function disk(): FilesystemAdapter
    {
        return Storage::disk(SolatProofService::Disk);
    }

    public function test_absen_dari_halaman_menyimpan_foto_bukti(): void
    {
        $this->seedPrayerTimes();
        $this->openZuhur();
        $member = $this->enrolledMember();
        $this->fakeFaceService(photo: self::ProofBytes);

        $this->scanFromPage()
            ->assertOk()
            ->assertJson(['ok' => true, 'status' => SolatAttendanceService::StatusRecorded])
            ->assertJsonPath('proof_url', fn (mixed $url): bool => is_string($url) && $url !== '');

        $log = SolatAttendanceLog::query()->firstOrFail();

        $this->assertSame($member->id, $log->user_id);
        $this->assertNotNull($log->proof_photo_path);
        $this->assertStringStartsWith(SolatProofService::Folder.'/', $log->proof_photo_path);
        $this->assertTrue($this->disk()->exists($log->proof_photo_path));
        $this->assertSame(self::ProofBytes, $this->disk()->get($log->proof_photo_path));
    }

    public function test_pemanggil_meminta_foto_bukti_ke_mesin_wajah(): void
    {
        $this->seedPrayerTimes();
        $this->openZuhur();
        $this->enrolledMember();
        $this->fakeFaceService(photo: self::ProofBytes);

        $layanan = app(FaceEncodingService::class);

        $this->scanFromPage()->assertOk();

        $this->assertTrue($layanan->askedForPhoto);
    }

    public function test_bukti_tidak_disimpan_saat_fitur_dimatikan(): void
    {
        config(['solat.keep_proof_photo' => false]);

        $this->seedPrayerTimes();
        $this->openZuhur();
        $this->enrolledMember();
        $this->fakeFaceService(photo: self::ProofBytes);

        $layanan = app(FaceEncodingService::class);

        $this->scanFromPage()->assertOk()->assertJson(['status' => SolatAttendanceService::StatusRecorded]);

        $this->assertFalse($layanan->askedForPhoto);

        $log = SolatAttendanceLog::query()->firstOrFail();

        $this->assertNull($log->proof_photo_path);
        $this->assertSame([], $this->disk()->allFiles());
    }

    public function test_absen_ulang_tidak_menimpa_bukti_lama(): void
    {
        $this->seedPrayerTimes();
        $this->openZuhur();
        $this->enrolledMember();
        $this->fakeFaceService(photo: self::ProofBytes);

        $this->scanFromPage()->assertOk();

        $path = SolatAttendanceLog::query()->firstOrFail()->proof_photo_path;

        $this->fakeFaceService(photo: "\xFF\xD8\xFF\xE0bukti kedua");

        $this->scanFromPage()
            ->assertOk()
            ->assertJson(['status' => SolatAttendanceService::StatusDuplicate]);

        $this->assertSame(1, SolatAttendanceLog::query()->count());
        $this->assertSame($path, SolatAttendanceLog::query()->firstOrFail()->proof_photo_path);
        $this->assertSame(self::ProofBytes, $this->disk()->get($path));
        $this->assertCount(1, $this->disk()->allFiles());
    }

    public function test_wajah_belum_dikenal_tidak_menyimpan_bukti(): void
    {
        $this->seedPrayerTimes();
        $this->openZuhur();
        $this->fakeFaceService(photo: self::ProofBytes);

        $this->scanFromPage()
            ->assertOk()
            ->assertJson(['ok' => false, 'status' => SolatAttendanceService::StatusUnknown]);

        $this->assertSame(0, SolatAttendanceLog::query()->count());
        $this->assertSame([], $this->disk()->allFiles());
    }

    public function test_bukti_dapat_dibuka_pemilik_petugas_dan_admin(): void
    {
        $this->seedPrayerTimes();
        $this->openZuhur();
        $member = $this->enrolledMember();
        $this->fakeFaceService(photo: self::ProofBytes);

        $this->scanFromPage()->assertOk();

        $log = SolatAttendanceLog::query()->firstOrFail();
        $alamat = route('attendance.proof', $log);

        foreach ([$member, $this->makeUser(User::RoleAdmin), $this->makeUser(User::RolePetugas)] as $pengunjung) {
            $this->actingAs($pengunjung)->get($alamat)->assertOk();
        }
    }

    public function test_bukti_anggota_lain_ditolak(): void
    {
        $this->seedPrayerTimes();
        $this->openZuhur();
        $this->enrolledMember();
        $this->fakeFaceService(photo: self::ProofBytes);

        $this->scanFromPage()->assertOk();

        $log = SolatAttendanceLog::query()->firstOrFail();

        $this->actingAs($this->makeUser(User::RoleMurid))
            ->get(route('attendance.proof', $log))
            ->assertForbidden();
    }

    public function test_bukti_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $this->seedPrayerTimes();
        $log = $this->proofLog($this->makeSession('zuhur'));

        $this->get(route('attendance.proof', $log))->assertRedirect(route('login'));
    }

    public function test_bukti_yang_berkasnya_hilang_menghasilkan_tidak_ditemukan(): void
    {
        $this->seedPrayerTimes();
        $this->openZuhur();
        $member = $this->enrolledMember();
        $this->fakeFaceService(photo: self::ProofBytes);

        $this->scanFromPage()->assertOk();

        $log = SolatAttendanceLog::query()->firstOrFail();
        $this->disk()->delete($log->proof_photo_path);

        $this->actingAs($member)
            ->get(route('attendance.proof', $log))
            ->assertNotFound();
    }

    public function test_riwayat_anggota_menampilkan_kolom_bukti(): void
    {
        $this->seedPrayerTimes();
        $this->openZuhur();
        $member = $this->enrolledMember();
        $this->fakeFaceService(photo: self::ProofBytes);

        $this->scanFromPage()->assertOk();

        $log = SolatAttendanceLog::query()->firstOrFail();

        $this->actingAs($member)
            ->get(route('murid.riwayat'))
            ->assertOk()
            ->assertSee('Bukti')
            ->assertSee(route('attendance.proof', $log), false);
    }

    public function test_daftar_sesi_menampilkan_bukti_atau_keterangan_tanpa_bukti(): void
    {
        $this->seedPrayerTimes();
        $session = $this->openZuhur();
        $this->enrolledMember();
        $this->fakeFaceService(photo: self::ProofBytes);

        $this->scanFromPage()->assertOk();

        $log = SolatAttendanceLog::query()->firstOrFail();
        $tanggal = $session->date->toDateString();

        $this->actingAs($this->makeUser(User::RoleAdmin))
            ->get(route('sessions.index', ['date' => $tanggal]))
            ->assertOk()
            ->assertSee('Bukti')
            ->assertSee(route('attendance.proof', $log), false);

        $log->forceFill(['proof_photo_path' => null])->save();

        $this->actingAs($this->makeUser(User::RoleAdmin))
            ->get(route('sessions.index', ['date' => $tanggal]))
            ->assertOk()
            ->assertSee('Tanpa bukti');
    }

    public function test_perintah_bersihkan_bukti_membuang_yang_kedaluwarsa(): void
    {
        $this->seedPrayerTimes();
        $session = $this->makeSession('zuhur');

        $lama = $this->proofLog($session, Carbon::now()->subDays(120));
        $baru = $this->proofLog($session, Carbon::now());

        $lamaPath = $lama->refresh()->proof_photo_path;
        $baruPath = $baru->refresh()->proof_photo_path;

        $this->assertNotNull($lamaPath);
        $this->assertNotNull($baruPath);
        $this->assertTrue($this->disk()->exists($lamaPath));

        $this->artisan('solat:bersihkan-bukti')->assertSuccessful();

        $this->assertNull($lama->refresh()->proof_photo_path);
        $this->assertFalse($this->disk()->exists($lamaPath));
        $this->assertSame($baruPath, $baru->refresh()->proof_photo_path);
        $this->assertTrue($this->disk()->exists($baruPath));
    }

    public function test_perintah_bersihkan_bukti_berhenti_saat_fitur_dimatikan(): void
    {
        config(['solat.keep_proof_photo' => false]);

        $this->seedPrayerTimes();

        $log = $this->proofLog($this->makeSession('zuhur'), Carbon::now()->subDays(120));
        $path = $log->refresh()->proof_photo_path;

        $this->assertNotNull($path);

        $this->artisan('solat:bersihkan-bukti')->assertSuccessful();

        $this->assertSame($path, $log->refresh()->proof_photo_path);
        $this->assertTrue($this->disk()->exists($path));
    }

    public function test_anggota_yang_dihapus_membersihkan_bukti_fotonya(): void
    {
        $this->seedPrayerTimes();

        $log = $this->proofLog($this->makeSession('zuhur'));
        $path = $log->refresh()->proof_photo_path;

        $this->assertNotNull($path);

        $this->actingAs($this->makeUser(User::RoleAdmin))
            ->delete(route('members.destroy', $log->user_id))
            ->assertRedirect();

        $this->assertSame(0, SolatAttendanceLog::query()->count());
        $this->assertFalse($this->disk()->exists($path));
        $this->assertSame([], $this->disk()->allFiles());
    }
}
