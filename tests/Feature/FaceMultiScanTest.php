<?php

namespace Tests\Feature;

use App\Models\FaceEmbedding;
use App\Models\SolatAttendanceLog;
use App\Models\SolatSession;
use App\Models\User;
use App\Services\FaceEncodingService;
use App\Services\SolatAttendanceService;
use App\Services\SolatSessionService;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Absen beberapa orang sekaligus dari satu bingkai kamera.
 *
 * Angka wajah di berkas ini dibuat sendiri: setiap anggota ditempatkan pada satu
 * sumbu angka yang berbeda, sehingga jarak antar anggota bisa ditentukan persis
 * dan hasil pencocokannya dapat dipastikan. Cara ini menguji ATURAN KEPUTUSAN
 * dan pemisahan hasil per wajah, BUKAN ketajaman mesin wajah di dunia nyata.
 *
 * Kotak wajah yang dikirim mesin wajah juga dipakai sebagai data uji, jadi
 * berkas ini sekaligus memastikan koordinat kotak tidak berubah bentuk di
 * sepanjang jalan sampai balasan halaman kamera.
 */
class FaceMultiScanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Cakram dipalsukan supaya berkas bukti dari pengujian tidak menumpuk
        // di penyimpanan asli aplikasi.
        Storage::fake('local');
    }

    private function attendance(): SolatAttendanceService
    {
        return app(SolatAttendanceService::class);
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk('local');
    }

    /**
     * Angka wajah dengan satu sumbu bernilai tertentu.
     *
     * Dua anggota pada sumbu yang berbeda berjarak akar dari jumlah kuadrat
     * nilainya, sehingga hasil pencocokan bisa dihitung dengan pasti.
     *
     * @return array<int, float>
     */
    private function spot(int $index, float $value = 0.2): array
    {
        $embedding = array_fill(0, FaceEmbedding::Dimensions, 0.0);
        $embedding[$index] = $value;

        return $embedding;
    }

    /**
     * Anggota beserta satu angka wajahnya pada sumbu tertentu.
     */
    private function memberOn(int $index, string $name, float $value = 0.2): User
    {
        $member = $this->makeUser(User::RoleMurid, ['name' => $name]);

        FaceEmbedding::create([
            'user_id' => $member->getKey(),
            'label' => 'Foto uji',
            'model' => FaceEmbedding::DefaultModel,
            'embedding' => $this->spot($index, $value),
        ]);

        return $member;
    }

    /**
     * Buka sesi zuhur seperti petugas sungguhan.
     */
    private function openZuhur(): SolatSession
    {
        $this->seedPrayerTimes();

        $session = $this->makeSession('zuhur');

        $this->travelTo(Carbon::parse('today 12:05'));
        app(SolatSessionService::class)->open($session, $this->makeUser(User::RoleAdmin));

        return $session->refresh();
    }

    private function petugas(): User
    {
        return $this->makeUser(User::RolePetugas, ['can_scan_face' => true]);
    }

    /**
     * Kirim satu potret ke halaman kamera petugas.
     */
    private function scanAs(User $petugas, ?string $file = null): TestResponse
    {
        return $this->actingAs($petugas)->post(route('attendance.wajah.scan'), [
            'photo' => UploadedFile::fake()->image($file ?? 'absen.jpg'),
        ]);
    }

    /**
     * Bentuk satu wajah pada balasan mesin wajah tiruan.
     *
     * @param  array<int, float>|null  $encoding
     * @return array<string, mixed>
     */
    private function face(?array $encoding, int $left, int $top = 100, int $size = 150): array
    {
        return [
            'encoding' => $encoding,
            'box' => [
                'top' => $top,
                'right' => $left + $size,
                'bottom' => $top + $size,
                'left' => $left,
            ],
            'face_size' => $size,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Beberapa wajah pada satu bingkai
    |--------------------------------------------------------------------------
    */

    public function test_satu_wajah_dikenal_lalu_tercatat(): void
    {
        $this->openZuhur();
        $anggota = $this->memberOn(0, 'Haris');

        $this->fakeFaceService(faces: [$this->face($this->spot(0), 50)]);

        $this->scanAs($this->petugas())
            ->assertOk()
            ->assertJson(['ok' => true, 'status' => SolatAttendanceService::StatusSuccessMulti])
            ->assertJsonPath('faces.0.status', SolatAttendanceService::StatusRecorded)
            ->assertJsonPath('faces.0.attendance.recorded', true)
            ->assertJsonPath('faces.0.label', 'Haris')
            ->assertJsonPath('faces.0.member.id', $anggota->getKey());

        $this->assertSame(1, SolatAttendanceLog::query()->count());
    }

    public function test_tiga_wajah_berbeda_pada_satu_bingkai_dilaporkan_sendiri_sendiri(): void
    {
        $this->openZuhur();

        $this->memberOn(0, 'Haris');
        $this->memberOn(1, 'Siswa B');
        $this->memberOn(2, 'Siswa C');

        $this->fakeFaceService(faces: [
            $this->face($this->spot(0), 40),
            $this->face($this->spot(1), 300),
            $this->face($this->spot(2), 560),
        ]);

        $balasan = $this->scanAs($this->petugas())->assertOk()->assertJsonCount(3, 'faces');

        $this->assertSame('Haris', $balasan->json('faces.0.label'));
        $this->assertSame('Siswa B', $balasan->json('faces.1.label'));
        $this->assertSame('Siswa C', $balasan->json('faces.2.label'));

        // Identitas mengikuti angka wajahnya, bukan urutan wajah di bingkai.
        foreach ([0, 1, 2] as $urutan) {
            $this->assertSame(
                SolatAttendanceService::StatusRecorded,
                $balasan->json("faces.$urutan.status"),
            );
        }

        // Koordinat kotak yang dikirim mesin wajah diteruskan apa adanya.
        $this->assertSame(
            ['top' => 100, 'right' => 450, 'bottom' => 250, 'left' => 300],
            $balasan->json('faces.1.box'),
        );

        $this->assertSame(3, SolatAttendanceLog::query()->count());
    }

    public function test_dua_wajah_dikenal_dan_satu_tidak_dikenal(): void
    {
        $this->openZuhur();

        $satu = $this->memberOn(0, 'Haris');
        $dua = $this->memberOn(1, 'Siswa B');

        // Wajah ketiga berada jauh dari seluruh angka wajah yang tersimpan.
        $this->fakeFaceService(faces: [
            $this->face($this->spot(0), 40),
            $this->face($this->spot(1), 300),
            $this->face($this->spot(9, 0.9), 560),
        ]);

        $balasan = $this->scanAs($this->petugas())->assertOk();

        $this->assertSame('Haris', $balasan->json('faces.0.label'));
        $this->assertSame('Siswa B', $balasan->json('faces.1.label'));
        $this->assertSame('Tidak dikenal', $balasan->json('faces.2.label'));
        $this->assertSame(SolatAttendanceService::StatusUnknown, $balasan->json('faces.2.status'));
        $this->assertFalse($balasan->json('faces.2.attendance.recorded'));

        // Wajah yang tidak dikenal tidak boleh menghentikan, dan tidak boleh
        // membuat catatan absen.
        $this->assertSame(2, SolatAttendanceLog::query()->count());
        $this->assertSame(
            [$satu->getKey(), $dua->getKey()],
            SolatAttendanceLog::query()->orderBy('user_id')->pluck('user_id')->all(),
        );
    }

    public function test_semua_wajah_tidak_dikenal_tidak_menghasilkan_absen(): void
    {
        $this->openZuhur();
        $this->memberOn(0, 'Haris');

        $this->fakeFaceService(faces: [
            $this->face($this->spot(9, 0.9), 40),
            $this->face($this->spot(10, 0.9), 300),
        ]);

        $this->scanAs($this->petugas())
            ->assertOk()
            ->assertJson(['ok' => false])
            ->assertJsonPath('faces.0.status', SolatAttendanceService::StatusUnknown)
            ->assertJsonPath('faces.1.status', SolatAttendanceService::StatusUnknown);

        $this->assertSame(0, SolatAttendanceLog::query()->count());
        $this->assertSame([], $this->disk()->allFiles());
    }

    public function test_tidak_ada_wajah_di_bingkai_berikutnya(): void
    {
        $this->openZuhur();
        $this->memberOn(0, 'Haris');

        $this->fakeFaceService(faces: [$this->face($this->spot(0), 50)]);

        $this->scanAs($this->petugas())->assertOk()->assertJsonCount(1, 'faces');

        // Bingkai berikutnya tidak memuat wajah: daftar wajahnya kosong, jadi
        // peramban tahu harus menghapus kotak dan label yang lama.
        $this->fakeFaceService(faceCount: 0);

        $this->scanAs($this->petugas())
            ->assertOk()
            ->assertJsonCount(0, 'faces')
            ->assertJsonPath('status', FaceEncodingService::StatusNoFace);

        $this->assertSame(1, SolatAttendanceLog::query()->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Pencegahan absensi ganda
    |--------------------------------------------------------------------------
    */

    public function test_wajah_yang_sama_berulang_dalam_banyak_bingkai_hanya_tercatat_sekali(): void
    {
        $this->openZuhur();
        $this->memberOn(0, 'Haris');

        $this->fakeFaceService(faces: [$this->face($this->spot(0), 50)], photo: "\xFF\xD8\xFF\xE0bukti");

        $petugas = $this->petugas();

        // Sepuluh bingkai berturut turut seperti kamera yang berjalan sendiri.
        for ($bingkai = 1; $bingkai <= 10; $bingkai++) {
            $balasan = $this->scanAs($petugas)->assertOk();

            $diharapkan = $bingkai === 1
                ? SolatAttendanceService::StatusRecorded
                : SolatAttendanceService::StatusDuplicate;

            $this->assertSame($diharapkan, $balasan->json('faces.0.status'), 'Bingkai ke-'.$bingkai);
            $this->assertSame($bingkai === 1, $balasan->json('faces.0.attendance.recorded'), 'Bingkai ke-'.$bingkai);
        }

        $this->assertSame(1, SolatAttendanceLog::query()->count());

        // Foto bukti juga tidak boleh ditumpuk ulang untuk absen yang sama.
        $this->assertCount(1, $this->disk()->allFiles());
    }

    public function test_permintaan_bersamaan_tidak_membuat_absen_ganda(): void
    {
        $this->openZuhur();
        $anggota = $this->memberOn(0, 'Haris');
        $session = SolatSession::query()->firstOrFail();

        // Kamera bisa mengirim dua bingkai nyaris bersamaan. Penjaga terakhirnya
        // adalah indeks unik (sesi, anggota) di basis data, bukan pemeriksaan di
        // peramban dan bukan pemeriksaan di aplikasi saja.
        SolatAttendanceLog::create([
            'user_id' => $anggota->getKey(),
            'solat_session_id' => $session->getKey(),
            'scanned_at' => Carbon::now(),
            'status' => SolatAttendanceLog::StatusHadir,
        ]);

        $this->expectException(QueryException::class);

        SolatAttendanceLog::create([
            'user_id' => $anggota->getKey(),
            'solat_session_id' => $session->getKey(),
            'scanned_at' => Carbon::now(),
            'status' => SolatAttendanceLog::StatusHadir,
        ]);
    }

    public function test_dua_siswa_bisa_absen_pada_bingkai_yang_sama(): void
    {
        $session = $this->openZuhur();

        $satu = $this->memberOn(0, 'Haris');
        $dua = $this->memberOn(1, 'Siswa B');

        $this->fakeFaceService(faces: [
            $this->face($this->spot(0), 40),
            $this->face($this->spot(1), 300),
        ], photo: "\xFF\xD8\xFF\xE0bukti");

        $this->scanAs($this->petugas())->assertOk()->assertJsonCount(2, 'faces');

        $catatan = SolatAttendanceLog::query()->orderBy('user_id')->get();

        $this->assertCount(2, $catatan);
        $this->assertSame([$satu->getKey(), $dua->getKey()], $catatan->pluck('user_id')->all());
        $this->assertTrue($catatan->every(fn ($log): bool => $log->solat_session_id === $session->getKey()));
    }

    /*
    |--------------------------------------------------------------------------
    | Kegagalan satu wajah tidak menghentikan wajah lain
    |--------------------------------------------------------------------------
    */

    public function test_wajah_yang_gagal_dibaca_tidak_menghentikan_wajah_lain(): void
    {
        $this->openZuhur();
        $anggota = $this->memberOn(0, 'Haris');

        // Wajah pertama angka wajahnya gagal dihitung mesin wajah.
        $this->fakeFaceService(faces: [
            $this->face(null, 40),
            $this->face($this->spot(0), 300),
        ]);

        $balasan = $this->scanAs($this->petugas())->assertOk();

        $this->assertSame(SolatAttendanceService::StatusNoFace, $balasan->json('faces.0.status'));
        $this->assertSame(SolatAttendanceService::LabelUnknown, $balasan->json('faces.0.label'));
        $this->assertSame(SolatAttendanceService::StatusRecorded, $balasan->json('faces.1.status'));
        $this->assertSame('Haris', $balasan->json('faces.1.label'));

        $this->assertSame(1, SolatAttendanceLog::query()->count());
        $this->assertSame($anggota->getKey(), SolatAttendanceLog::query()->firstOrFail()->user_id);
    }

    public function test_wajah_yang_terlalu_kecil_ditolak_tanpa_mengganggu_yang_lain(): void
    {
        $this->openZuhur();
        $this->memberOn(0, 'Haris');

        $this->fakeFaceService(faces: [
            $this->face($this->spot(0), 40, size: 20),
            $this->face($this->spot(0), 300, size: 200),
        ]);

        $balasan = $this->scanAs($this->petugas())->assertOk();

        $this->assertSame(SolatAttendanceService::StatusFaceTooSmall, $balasan->json('faces.0.status'));
        $this->assertSame('Dekatkan wajah', $balasan->json('faces.0.attendance.display_message'));
        $this->assertSame(SolatAttendanceService::StatusRecorded, $balasan->json('faces.1.status'));

        $this->assertSame(1, SolatAttendanceLog::query()->count());
    }

    public function test_hasil_ambigu_tidak_menghasilkan_absen(): void
    {
        $this->openZuhur();

        // Dua anggota berjarak hampir sama dari angka wajah yang dipindai.
        $this->memberOn(0, 'Haris', 0.20);
        $this->memberOn(0, 'Siswa B', 0.21);

        $this->fakeFaceService(faces: [$this->face($this->spot(0, 0.205), 40)]);

        $this->scanAs($this->petugas())
            ->assertOk()
            ->assertJsonPath('faces.0.status', SolatAttendanceService::StatusAmbiguous)
            ->assertJsonPath('faces.0.attendance.recorded', false);

        $this->assertSame(0, SolatAttendanceLog::query()->count());
    }

    public function test_layanan_wajah_mati_dilaporkan_tanpa_kotak(): void
    {
        $this->openZuhur();
        $this->memberOn(0, 'Haris');
        $this->fakeFaceService(available: false);

        $this->scanAs($this->petugas())
            ->assertOk()
            ->assertJson(['ok' => false, 'status' => FaceEncodingService::StatusUnavailable])
            ->assertJsonCount(0, 'faces');

        $this->assertSame(0, SolatAttendanceLog::query()->count());
    }

    public function test_sesi_belum_dibuka_ditolak(): void
    {
        $this->seedPrayerTimes();
        $this->makeSession('zuhur');
        $this->memberOn(0, 'Haris');

        $this->fakeFaceService(faces: [$this->face($this->spot(0), 40)]);

        $this->scanAs($this->petugas())
            ->assertOk()
            ->assertJson(['ok' => false, 'status' => SolatAttendanceService::StatusNoSession])
            ->assertJsonCount(0, 'faces');

        $this->assertSame(0, SolatAttendanceLog::query()->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Foto bukti
    |--------------------------------------------------------------------------
    */

    public function test_satu_foto_bukti_dipakai_bersama_oleh_semua_wajah_pada_bingkai_itu(): void
    {
        $this->openZuhur();

        $this->memberOn(0, 'Haris');
        $this->memberOn(1, 'Siswa B');

        $this->fakeFaceService(faces: [
            $this->face($this->spot(0), 40),
            $this->face($this->spot(1), 300),
        ], photo: "\xFF\xD8\xFF\xE0bukti satu bingkai");

        $this->scanAs($this->petugas())->assertOk();

        $catatan = SolatAttendanceLog::query()->get();

        $this->assertCount(2, $catatan);
        $this->assertNotNull($catatan[0]->proof_photo_path);
        $this->assertSame($catatan[0]->proof_photo_path, $catatan[1]->proof_photo_path);
        $this->assertCount(1, $this->disk()->allFiles());
        $this->assertSame(
            "\xFF\xD8\xFF\xE0bukti satu bingkai",
            $this->disk()->get($catatan[0]->proof_photo_path),
        );
    }

    public function test_foto_bukti_bersama_tidak_dihapus_selama_masih_dipakai(): void
    {
        $this->openZuhur();

        $satu = $this->memberOn(0, 'Haris');
        $dua = $this->memberOn(1, 'Siswa B');

        $this->fakeFaceService(faces: [
            $this->face($this->spot(0), 40),
            $this->face($this->spot(1), 300),
        ], photo: "\xFF\xD8\xFF\xE0bukti bersama");

        $this->scanAs($this->petugas())->assertOk();

        $path = SolatAttendanceLog::query()->firstOrFail()->proof_photo_path;

        // Menghapus catatan satu orang tidak boleh membuat bukti milik orang
        // lain yang memakai berkas yang sama ikut hilang.
        SolatAttendanceLog::query()->where('user_id', $satu->getKey())->delete();

        $this->assertTrue($this->disk()->exists($path));
        $this->assertSame(
            $path,
            SolatAttendanceLog::query()->where('user_id', $dua->getKey())->firstOrFail()->proof_photo_path,
        );
    }

    public function test_foto_bukti_tidak_dibuat_saat_tidak_ada_yang_tercatat(): void
    {
        $this->openZuhur();
        $this->memberOn(0, 'Haris');

        $this->fakeFaceService(faces: [$this->face($this->spot(9, 0.9), 40)], photo: "\xFF\xD8\xFF\xE0bukti");

        $this->scanAs($this->petugas())->assertOk()->assertJson(['ok' => false]);

        $this->assertSame([], $this->disk()->allFiles());
    }

    public function test_meminta_foto_bukti_ke_mesin_wajah_ikut_tercatat(): void
    {
        $this->openZuhur();
        $this->memberOn(0, 'Haris');

        $this->fakeFaceService(faces: [$this->face($this->spot(0), 40)]);

        /** @var FaceEncodingService $layanan */
        $layanan = app(FaceEncodingService::class);

        $this->scanAs($this->petugas())->assertOk();

        // Kedua wajah pada bingkai itu diperiksa lewat satu permintaan saja ke
        // mesin wajah, bukan satu permintaan per wajah.
        $this->assertTrue($layanan->askedForPhoto);
    }
}
