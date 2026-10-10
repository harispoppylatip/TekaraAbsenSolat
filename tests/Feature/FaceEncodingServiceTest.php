<?php

namespace Tests\Feature;

use App\Models\FaceEmbedding;
use App\Services\FaceEncodingService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Percakapan aplikasi dengan mesin wajah Python.
 *
 * Yang diuji di sini hanya bagian foto buktinya: kapan fotonya diminta, dan
 * balasan seperti apa yang diterima. Semua balasan yang mencurigakan harus
 * diabaikan tanpa menggagalkan absennya.
 */
class FaceEncodingServiceTest extends TestCase
{
    /** Foto JPEG paling sederhana yang masih punya penanda awal berkas. */
    private const JpegBytes = "\xFF\xD8\xFF\xE0potret bukti uji";

    /**
     * Siapkan balasan mesin wajah yang wajar.
     *
     * @param  array<string, mixed>  $tambahan
     */
    private function fakeService(array $tambahan = []): void
    {
        Http::fake([
            '*/encode*' => Http::response(array_merge([
                'status' => 'success',
                'encoding' => array_fill(0, FaceEmbedding::Dimensions, 0.02),
                'face_count' => 1,
            ], $tambahan), 200),
        ]);
    }

    private function service(): FaceEncodingService
    {
        return app(FaceEncodingService::class);
    }

    public function test_foto_bukti_diminta_ke_mesin_wajah_saat_diperlukan(): void
    {
        $this->fakeService(['photo' => base64_encode(self::JpegBytes)]);

        $hasil = $this->service()->encodeContents('potret', true);

        Http::assertSent(fn (Request $permintaan): bool => str_contains($permintaan->url(), 'with_photo=1'));

        $this->assertSame(FaceEncodingService::StatusSuccess, $hasil['status']);
        $this->assertSame(self::JpegBytes, $hasil['photo']);
    }

    public function test_mesin_wajah_tidak_diganggu_permintaan_foto_saat_tidak_perlu(): void
    {
        $this->fakeService(['photo' => base64_encode(self::JpegBytes)]);

        $hasil = $this->service()->encodeContents('potret');

        Http::assertSent(fn (Request $permintaan): bool => ! str_contains($permintaan->url(), 'with_photo'));

        $this->assertSame(FaceEncodingService::StatusSuccess, $hasil['status']);
        $this->assertNull($hasil['photo']);
    }

    public function test_balasan_tanpa_foto_dianggap_tanpa_bukti(): void
    {
        $this->fakeService();

        $hasil = $this->service()->encodeContents('potret', true);

        $this->assertSame(FaceEncodingService::StatusSuccess, $hasil['status']);
        $this->assertNull($hasil['photo']);
    }

    public function test_bukti_kosong_diabaikan(): void
    {
        $this->fakeService(['photo' => '']);

        $this->assertNull($this->service()->encodeContents('potret', true)['photo']);
    }

    public function test_bukti_yang_bukan_base64_diabaikan(): void
    {
        $this->fakeService(['photo' => '!!! bukan base64 !!!']);

        $this->assertNull($this->service()->encodeContents('potret', true)['photo']);
    }

    public function test_bukti_yang_bukan_jpeg_diabaikan(): void
    {
        $this->fakeService(['photo' => base64_encode('berkas ini bukan gambar')]);

        $this->assertNull($this->service()->encodeContents('potret', true)['photo']);
    }

    public function test_bukti_yang_kelebihan_ukuran_diabaikan(): void
    {
        $besar = "\xFF\xD8\xFF".str_repeat('a', (int) config('solat.proof_max_bytes', 262144) + 10);

        $this->fakeService(['photo' => base64_encode($besar)]);

        $this->assertNull($this->service()->encodeContents('potret', true)['photo']);
    }

    public function test_bukti_tepat_pada_batas_ukuran_masih_diterima(): void
    {
        $batas = (int) config('solat.proof_max_bytes', 262144);
        $pas = "\xFF\xD8\xFF".str_repeat('a', $batas - 3);

        $this->fakeService(['photo' => base64_encode($pas)]);

        $this->assertSame($pas, $this->service()->encodeContents('potret', true)['photo']);
    }

    public function test_wajah_tidak_terbaca_tidak_menyertakan_bukti(): void
    {
        $this->fakeService(['status' => 'no_face', 'encoding' => null, 'photo' => base64_encode(self::JpegBytes)]);

        $hasil = $this->service()->encodeContents('potret', true);

        $this->assertSame(FaceEncodingService::StatusNoFace, $hasil['status']);
        $this->assertNull($hasil['photo']);
    }
}
