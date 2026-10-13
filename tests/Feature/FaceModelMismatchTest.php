<?php

namespace Tests\Feature;

use App\Models\FaceEmbedding;
use App\Models\User;
use App\Services\FaceEncodingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pemberitahuan versi mesin wajah yang berbeda dari yang diharapkan aplikasi.
 *
 * Keadaan ini benar benar terjadi: berkas `.env` TIDAK ikut `git pull` sebab
 * ada di `.gitignore`, sehingga setelah proyek dipindah ke server, aplikasi
 * masih mengharapkan model lama sementara mesin wajahnya sudah memakai model
 * baru. Akibatnya setiap unggahan ditolak, dan pesannya harus cukup jelas
 * supaya penyebabnya tidak perlu ditebak.
 *
 * Pengujian ini mengunci tiga hal: pesannya menyebut KEDUA nama model, halaman
 * Data Wajah memberitahukannya SEBELUM foto diunggah, dan keadaannya tidak
 * menghalangi apa pun bila modelnya memang sama.
 */
class FaceModelMismatchTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return $this->makeUser(User::RoleAdmin);
    }

    private function member(): User
    {
        return $this->makeUser(User::RoleMurid);
    }

    /*
    |--------------------------------------------------------------------------
    | Pesan penolakan
    |--------------------------------------------------------------------------
    */

    public function test_pesan_penolakan_menyebut_model_yang_diharapkan_dan_yang_dilaporkan(): void
    {
        // Sambungan HTTP-nya yang dipalsukan, BUKAN seluruh layanan wajahnya.
        // Pemeriksaan nama model berada DI DALAM FaceEncodingService, jadi
        // mengganti layanan dengan tiruan justru melewati kode yang sedang
        // diuji dan pengujiannya selalu lolos tanpa membuktikan apa pun.
        Http::fake([
            '*/encode*' => Http::response([
                'status' => 'success',
                'encoding' => array_fill(0, FaceEmbedding::Dimensions, 0.01),
                'face_count' => 1,
                'face_size' => 200,
                'model' => 'dlib-resnet-v1',
                'message' => 'Wajah terbaca.',
            ]),
        ]);

        $hasil = app(FaceEncodingService::class)->encodeContents('potret uji');

        $this->assertSame(FaceEncodingService::StatusInvalid, $hasil['status']);
        $this->assertNull($hasil['encoding']);

        // Kedua nama WAJIB ada, sebab tanpa keduanya operator hanya tahu ada
        // yang tidak cocok tetapi tidak tahu mana yang harus disamakan.
        $this->assertStringContainsString(FaceEmbedding::DefaultModel, $hasil['message']);
        $this->assertStringContainsString('dlib-resnet-v1', $hasil['message']);
        $this->assertStringContainsString('FACE_MODEL', $hasil['message']);
    }

    public function test_angka_wajah_dari_model_sendiri_tetap_diterima(): void
    {
        Http::fake([
            '*/encode*' => Http::response([
                'status' => 'success',
                'encoding' => array_fill(0, FaceEmbedding::Dimensions, 0.01),
                'face_count' => 1,
                'face_size' => 200,
                'model' => FaceEmbedding::DefaultModel,
                'message' => 'Wajah terbaca.',
            ]),
        ]);

        $hasil = app(FaceEncodingService::class)->encodeContents('potret uji');

        $this->assertSame(FaceEncodingService::StatusSuccess, $hasil['status']);
        $this->assertCount(FaceEmbedding::Dimensions, $hasil['encoding']);
    }

    public function test_jalur_banyak_wajah_menolak_dengan_pesan_yang_sama(): void
    {
        Http::fake([
            '*/detect*' => Http::response([
                'status' => 'success',
                'face_count' => 1,
                'width' => 640,
                'height' => 480,
                'model' => 'dlib-resnet-v1',
                'message' => 'Wajah terbaca.',
                'faces' => [[
                    'index' => 0,
                    'bbox' => ['top' => 10, 'right' => 100, 'bottom' => 100, 'left' => 10],
                    'face_size' => 90,
                    'encoding' => array_fill(0, FaceEmbedding::Dimensions, 0.01),
                    'error' => null,
                ]],
            ]),
        ]);

        $hasil = app(FaceEncodingService::class)->detectContents('potret uji');

        $this->assertStringContainsString(FaceEmbedding::DefaultModel, $hasil['message']);
        $this->assertStringContainsString('dlib-resnet-v1', $hasil['message']);
    }

    /*
    |--------------------------------------------------------------------------
    | Pemberitahuan di halaman
    |--------------------------------------------------------------------------
    */

    public function test_halaman_memberitahukan_versi_yang_berbeda_sebelum_foto_diunggah(): void
    {
        $this->actingAs($this->admin());
        $this->fakeFaceService(model: 'dlib-resnet-v1');

        $this->get(route('face.index'))
            ->assertOk()
            ->assertSee('Versi mesin wajah tidak sama dengan aplikasi')
            ->assertSee(FaceEmbedding::DefaultModel)
            ->assertSee('dlib-resnet-v1')
            ->assertSee('FACE_MODEL_NAME');
    }

    public function test_halaman_tidak_memberitahukan_apa_apa_bila_modelnya_sama(): void
    {
        $this->actingAs($this->admin());
        $this->fakeFaceService();

        $this->get(route('face.index'))
            ->assertOk()
            ->assertDontSee('Versi mesin wajah tidak sama dengan aplikasi')
            ->assertSee('Layanan wajah siap')
            ->assertSee(FaceEmbedding::DefaultModel);
    }

    public function test_halaman_tetap_memberitahukan_layanan_mati_walau_model_belum_diketahui(): void
    {
        $this->actingAs($this->admin());
        $this->fakeFaceService(available: false);

        $this->get(route('face.index'))
            ->assertOk()
            ->assertSee('Layanan wajah tidak menyala')
            ->assertDontSee('Versi mesin wajah tidak sama dengan aplikasi');
    }

    /*
    |--------------------------------------------------------------------------
    | Mesin tiruan tidak boleh menunggu jaringan
    |--------------------------------------------------------------------------
    */

    public function test_keadaan_layanan_dibaca_dari_mesin_tiruan_bukan_dari_jaringan(): void
    {
        // Tanpa penimpaan `health()`, pengujian ini akan menghubungi layanan
        // sungguhan lewat HTTP dan hasilnya berbeda antara komputer yang mesin
        // wajahnya menyala dan yang tidak.
        $this->fakeFaceService();

        $sehat = app(FaceEncodingService::class)->health();

        $this->assertSame(FaceEmbedding::DefaultModel, $sehat['model']);
        $this->assertTrue($sehat['ready']);
        $this->assertSame(FaceEmbedding::Dimensions, $sehat['dimensions']);

        $this->fakeFaceService(available: false);

        $this->assertNull(app(FaceEncodingService::class)->health());
    }
}
