<?php

namespace Tests\Feature;

use App\Models\FaceEmbedding;
use App\Models\FacePhotoUpload;
use App\Models\User;
use App\Services\FaceEncodingService;
use App\Services\StudentEnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pemeriksaan mutu foto di sisi aplikasi.
 *
 * Pemeriksaan mutunya sendiri dikerjakan mesin wajah Python: kecerahan,
 * ketajaman, dan arah hadap. Yang diuji di sini adalah bagian aplikasinya:
 * apakah permintaannya benar-benar meminta pemeriksaan itu, apakah jawaban
 * penolakan mesin disampaikan apa adanya kepada pemilik foto, dan apakah alur
 * absen di gerbang TIDAK ikut berubah karenanya.
 *
 * Sambungan HTTP-nya yang dipalsukan, BUKAN seluruh layanan wajahnya. Mengganti
 * layanan dengan tiruan akan melewati justru kode yang sedang diuji.
 */
class FaceQualityResponseTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Balasan mesin wajah untuk satu permintaan.
     *
     * @param  array<string, mixed>  $ubah
     */
    private function balas(array $ubah = []): void
    {
        Http::fake([
            '*/encode*' => Http::response(array_merge([
                'status' => 'success',
                'encoding' => array_fill(0, FaceEmbedding::Dimensions, 0.01),
                'face_count' => 1,
                'face_size' => 200,
                'model' => (string) config('face.model'),
                'message' => 'Wajah terbaca.',
            ], $ubah)),
        ]);
    }

    /**
     * Alamat permintaan terakhir yang dikirim ke mesin wajah.
     */
    private function alamatTerakhir(): string
    {
        foreach (Http::recorded() as $pasangan) {
            return (string) $pasangan[0]->url();
        }

        return '';
    }

    public function test_jalur_absen_tidak_meminta_pemeriksaan_mutu(): void
    {
        $this->balas();

        app(FaceEncodingService::class)->encodeContents('potret uji');

        $alamat = $this->alamatTerakhir();

        $this->assertStringContainsString('require_single=1', $alamat);
        $this->assertStringNotContainsString('quality=1', $alamat);
    }

    public function test_jalur_pendaftaran_meminta_pemeriksaan_mutu(): void
    {
        $this->balas();

        app(FaceEncodingService::class)->encodeForEnrollment('potret uji');

        $this->assertStringContainsString('quality=1', $this->alamatTerakhir());
    }

    /**
     * Daftar penolakan mutu beserta pesan yang diharapkan.
     *
     * Dipasang lewat atribut, bukan anotasi, sebab PHPUnit 12 yang dipakai
     * proyek ini sudah tidak menjalankan anotasi `@dataProvider`. Anotasinya
     * gagal secara diam diam: tesnya tetap dijalankan, tetapi TANPA argumen,
     * sehingga yang muncul adalah galat argumen yang membingungkan dan bukan
     * tes yang benar benar menguji empat jenis penolakan itu.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public static function penolakanMutu(): array
    {
        return [
            [FaceEncodingService::StatusTooDark, 'Fotonya terlalu gelap.'],
            [FaceEncodingService::StatusTooBright, 'Fotonya terlalu terang.'],
            [FaceEncodingService::StatusBlurry, 'Fotonya kurang tajam.'],
            [FaceEncodingService::StatusBadPose, 'Wajah terlalu mendongak.'],
        ];
    }

    #[DataProvider('penolakanMutu')]
    public function test_penolakan_mutu_disampaikan_apa_adanya(string $dariMesin, string $pesan): void
    {
        $this->balas([
            'status' => $dariMesin,
            'encoding' => null,
            'message' => $pesan,
        ]);

        $hasil = app(FaceEncodingService::class)->encodeForEnrollment('potret uji');

        $this->assertSame($dariMesin, $hasil['status']);
        $this->assertSame($pesan, $hasil['message']);
        $this->assertNull($hasil['encoding']);
    }

    public function test_penolakan_mutu_tanpa_pesan_tetap_memberi_petunjuk(): void
    {
        $this->balas([
            'status' => FaceEncodingService::StatusBlurry,
            'encoding' => null,
            'message' => '',
        ]);

        $hasil = app(FaceEncodingService::class)->encodeForEnrollment('potret uji');

        $this->assertSame(FaceEncodingService::StatusBlurry, $hasil['status']);
        $this->assertStringContainsString('Ambil ulang', $hasil['message']);
    }

    public function test_jalur_absen_tidak_ikut_ditolak_oleh_mutu_foto(): void
    {
        // Mesin wajah MENOLAK karena mutunya, padahal pemeriksaan mutu tidak
        // diminta. Balasannya harus diperlakukan seperti balasan tak dikenal,
        // bukan sebagai penolakan mutu, sebab alur absen tidak boleh berubah
        // hanya karena ambang mutu disetel untuk pendaftaran.
        $this->balas([
            'status' => FaceEncodingService::StatusTooDark,
            'encoding' => null,
            'message' => 'Fotonya terlalu gelap.',
        ]);

        $hasil = app(FaceEncodingService::class)->encodeContents('potret uji');

        $this->assertSame(FaceEncodingService::StatusInvalid, $hasil['status']);
    }

    public function test_mutu_foto_yang_ditolak_tidak_menyimpan_angka_wajah(): void
    {
        Storage::fake('local');

        $murid = $this->makeUser(User::RoleMurid);

        $this->balas([
            'status' => FaceEncodingService::StatusTooDark,
            'encoding' => null,
            'message' => 'Fotonya terlalu gelap. Cari tempat yang lebih terang, lalu ambil ulang.',
        ]);

        $this->actingAs($murid)
            ->post(route('enrollment.photos.store'), [
                'photos' => [UploadedFile::fake()->createWithContent('gelap.jpg', 'isi foto')],
            ])
            ->assertRedirect();

        $this->assertSame(0, $murid->refresh()->faceEmbeddings()->count());
        $this->assertStringContainsString(
            'terlalu gelap',
            (string) FacePhotoUpload::query()->latest('id')->value('message'),
        );
    }

    public function test_batas_jumlah_foto_mengikuti_pengaturan_pendaftaran(): void
    {
        $layanan = app(StudentEnrollmentService::class);

        $this->assertSame((int) config('face.enrollment.required_photos'), $layanan->requiredPhotos());
        $this->assertSame((int) config('face.enrollment.max_photos'), $layanan->maxPhotos());
        $this->assertSame((int) config('face.enrollment.max_photos_per_submit'), $layanan->maxPerSubmit());

        // Lima variasi pertama wajib, sisanya tambahan.
        $variasi = $layanan->variations();

        $this->assertCount(10, $variasi);
        $this->assertTrue($variasi[0]['required']);
        $this->assertTrue($variasi[4]['required']);
        $this->assertFalse($variasi[5]['required']);
    }

    public function test_ambang_foto_kembar_berada_di_bawah_ambang_pencocokan(): void
    {
        $kembar = (float) config('face.enrollment.duplicate_tolerance');

        // Ambang foto kembar wajib JAUH lebih ketat daripada ambang pencocokan
        // identitas. Kalau angkanya terbalik, foto sah yang berbeda sudut akan
        // ditolak sebagai foto kembar dan pendaftaran tidak akan pernah selesai.
        $this->assertGreaterThan(0.0, $kembar);
        $this->assertLessThan((float) config('face.tolerance') / 2, $kembar);
    }

    public function test_keadaan_pendaftaran_bawaan_adalah_belum_mendaftar(): void
    {
        $murid = $this->makeUser(User::RoleMurid);

        $this->assertSame(User::EnrollmentNotStarted, $murid->enrollmentStatus());
        $this->assertSame('Belum mendaftar', $murid->enrollmentLabel());
        $this->assertTrue($murid->needsEnrollment());
        $this->assertFalse($murid->hasCompletedEnrollment());
    }
}
