<?php

namespace Tests\Feature;

use App\Jobs\EncodeFacePhoto;
use App\Models\Device;
use App\Models\FaceEmbedding;
use App\Models\FacePhotoUpload;
use App\Models\User;
use App\Services\FaceEncodingService;
use App\Services\FaceEnrollmentService;
use App\Services\FacePhotoQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Aturan penyimpanan foto wajah dan penggantian kumpulan foto.
 *
 * Yang dikunci di sini: foto yang gagal diproses tidak pernah menjadi data
 * wajah, foto yang gagal tidak meninggalkan berkas sementara, seluruh foto
 * baru yang berhasil dipertahankan saat penggantian, dan foto lama tidak
 * pernah hilang karena foto baru yang gagal.
 */
class FaceStoreRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Foto yang menunggu diproses ditulis ke cakram, jadi cakramnya dipalsukan
        // supaya pengujian tidak menumpuk berkas di penyimpanan sungguhan.
        Storage::fake('local');

        // Antrean dipalsukan supaya pengujian yang menentukan sendiri kapan
        // setiap foto dikerjakan. Tanpa itu setiap foto langsung diproses di
        // dalam permintaan halaman, dan keadaan setengah jalan tidak bisa
        // diperiksa.
        Queue::fake();
    }

    private function admin(): User
    {
        return $this->makeUser(User::RoleAdmin);
    }

    private function member(string $name = 'Haris', string $identifier = '25001'): User
    {
        return $this->makeUser(User::RoleMurid, ['name' => $name, 'identifier_number' => $identifier]);
    }

    /**
     * Foto palsu yang benar benar punya isi berkas. `create()` menghasilkan
     * berkas kosong sehingga mesin wajah selalu menjawab "foto tidak terbaca".
     */
    private function photo(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, 'isi potret uji '.$name);
    }

    /**
     * Layanan wajah tiruan yang menjawab berbeda untuk setiap foto.
     *
     * Jawabannya diambil berurutan sesuai urutan foto masuk antrean. Di
     * pengujian antreannya dikerjakan langsung, jadi urutannya pasti. Satu
     * entri bernilai null berarti foto itu berhasil, sedangkan entri berisi nama
     * status berarti mesin wajah menolaknya dengan status itu.
     *
     * @param  array<int, string|null>  $answers
     */
    private function faceServiceAnswering(array $answers, int $faceSize = 0): void
    {
        $this->app->instance(FaceEncodingService::class, new class($answers, $faceSize) extends FaceEncodingService
        {
            /** Berapa foto yang sudah dinilai, dipakai mengambil jawaban berikutnya. */
            public int $calls = 0;

            /**
             * @param  array<int, string|null>  $answers
             */
            public function __construct(private array $answers, private int $faceSize) {}

            public function isAvailable(): bool
            {
                return true;
            }

            public function encode(UploadedFile $image, bool $withPhoto = false): array
            {
                return $this->encodeContents('potret uji', $withPhoto);
            }

            public function encodeForEnrollment(string $contents): array
            {
                return $this->encodeContents($contents);
            }

            public function encodeContents(string $contents, bool $withPhoto = false): array
            {
                $answer = $this->answers[$this->calls] ?? null;
                $this->calls++;

                if ($answer === null) {
                    $encoding = array_fill(0, FaceEmbedding::Dimensions, 0.0);
                    $encoding[$this->calls % FaceEmbedding::Dimensions] = 0.5;

                    return [
                        'status' => self::StatusSuccess,
                        'encoding' => $encoding,
                        'face_count' => 1,
                        'face_size' => $this->faceSize,
                        'model' => null,
                        'message' => 'Wajah terbaca.',
                        'photo' => null,
                    ];
                }

                return [
                    'status' => $answer,
                    'encoding' => null,
                    'face_count' => 0,
                    'face_size' => 0,
                    'model' => null,
                    'message' => 'Balasan uji '.$answer.'.',
                    'photo' => null,
                ];
            }
        });
    }

    /**
     * Titipkan beberapa foto lewat halaman, lalu kembalikan barisnya.
     *
     * @param  array<int, string>  $names
     * @return array<int, FacePhotoUpload>
     */
    private function upload(User $actor, User $member, array $names, bool $replace = false): array
    {
        $photos = [];

        foreach ($names as $name) {
            $photos[] = $this->photo($name);
        }

        $this->actingAs($actor)->post(route('face.photos.store'), array_filter([
            'user_id' => $member->getKey(),
            'photos' => $photos,
            'replace' => $replace ? 1 : null,
        ]))->assertRedirect()->assertSessionHasNoErrors();

        return FacePhotoUpload::query()->orderBy('id')->get()->all();
    }

    /**
     * Kerjakan seluruh baris yang masih menunggu, berurutan seperti pekerja
     * antrean sungguhan.
     */
    private function workAll(): void
    {
        foreach (FacePhotoUpload::query()->orderBy('id')->get() as $row) {
            if ($row->status === FacePhotoUpload::StatusMenunggu) {
                $this->work($row);
            }
        }
    }

    private function work(FacePhotoUpload $row): void
    {
        (new EncodeFacePhoto((int) $row->getKey()))->handle(
            app(FaceEnrollmentService::class),
            app(FacePhotoQueueService::class),
        );
    }

    /**
     * @return array<int, string|null>
     */
    private function labels(User $member): array
    {
        return $member->faceEmbeddings()->orderBy('id')->pluck('label')->all();
    }

    /*
    |--------------------------------------------------------------------------
    | A. Foto yang gagal tidak boleh tersimpan
    |--------------------------------------------------------------------------
    */

    public function test_enam_foto_dengan_dua_gagal_hanya_menyimpan_empat(): void
    {
        $admin = $this->admin();
        $member = $this->member();

        // Foto 2 tidak ada wajahnya, foto 5 wajahnya terlalu kecil.
        $this->faceServiceAnswering([
            null,
            FaceEncodingService::StatusNoFace,
            null,
            null,
            FaceEncodingService::StatusFaceTooSmall,
            null,
        ]);

        $this->upload($admin, $member, ['a.jpg', 'b.jpg', 'c.jpg', 'd.jpg', 'e.jpg', 'f.jpg']);

        $this->workAll();

        $this->assertSame(4, $member->faceEmbeddings()->count(), 'Hanya foto yang berhasil yang tersimpan.');
        $this->assertSame(4, FacePhotoUpload::query()->where('status', FacePhotoUpload::StatusBerhasil)->count());
        $this->assertSame(2, FacePhotoUpload::query()->where('status', FacePhotoUpload::StatusGagal)->count());

        $this->assertSame(['a.jpg', 'c.jpg', 'd.jpg', 'f.jpg'], $this->labels($member));
    }

    public function test_tidak_ada_wajah_menu_tidak_menyimpan_data_wajah(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        $this->faceServiceAnswering([FaceEncodingService::StatusNoFace]);

        $this->upload($admin, $member, ['a.jpg']);
        $this->workAll();

        $this->assertSame(0, FaceEmbedding::query()->count());
        $this->assertSame(0, $member->faceEmbeddings()->count());
    }

    public function test_wajah_terlalu_kecil_menunda_tidak_menyimpan_data_wajah(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        $this->faceServiceAnswering([FaceEncodingService::StatusFaceTooSmall]);

        $this->upload($admin, $member, ['kecil.jpg']);
        $this->workAll();

        $this->assertSame(0, FaceEmbedding::query()->count());

        $row = FacePhotoUpload::query()->firstOrFail();

        $this->assertSame(FacePhotoUpload::StatusGagal, $row->status);
        $this->assertNull($row->face_embedding_id, 'Foto gagal tidak boleh menunjuk baris angka wajah.');
    }

    public function test_angka_wajah_yang_gagal_dibuat_tidak_menyimpan_data_wajah(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        $this->faceServiceAnswering([FaceEncodingService::StatusInvalid]);

        $this->upload($admin, $member, ['rusak.jpg']);
        $this->workAll();

        $this->assertSame(0, FaceEmbedding::query()->count());
        $this->assertNull(FacePhotoUpload::query()->firstOrFail()->face_embedding_id);
    }

    public function test_kegagalan_satu_foto_tidak_menghentikan_foto_lain(): void
    {
        $admin = $this->admin();
        $member = $this->member();

        // Foto pertama berhasil, foto kedua gagal, foto ketiga harus tetap jalan.
        $this->faceServiceAnswering([null, FaceEncodingService::StatusNoFace, null]);

        $rows = $this->upload($admin, $member, ['satu.jpg', 'dua.jpg', 'tiga.jpg']);

        $this->work($rows[0]);
        $this->work($rows[1]);
        $this->work($rows[2]);

        $this->assertSame(['satu.jpg', 'tiga.jpg'], $this->labels($member));
        $this->assertSame(FacePhotoUpload::StatusBerhasil, $rows[0]->refresh()->status);
        $this->assertSame(FacePhotoUpload::StatusGagal, $rows[1]->refresh()->status);
        $this->assertSame(FacePhotoUpload::StatusBerhasil, $rows[2]->refresh()->status);
    }

    public function test_foto_yang_gagal_tidak_meninggalkan_berkas_sementara(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        $this->faceServiceAnswering([null, FaceEncodingService::StatusNoFace]);

        $this->upload($admin, $member, ['a.jpg', 'b.jpg']);
        $this->workAll();

        $this->assertSame([], Storage::disk('local')->allFiles(), 'Berkas sementara tidak boleh tertinggal.');
    }

    public function test_layanan_wajah_yang_mati_menyisakan_berkas_untuk_diulang(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        $this->faceServiceAnswering([FaceEncodingService::StatusUnavailable]);

        $this->upload($admin, $member, ['a.jpg']);

        $row = FacePhotoUpload::query()->firstOrFail();

        $this->work($row);

        $this->assertSame(0, FaceEmbedding::query()->count());
        $this->assertSame(1, count(Storage::disk('local')->allFiles()), 'Fotonya disimpan supaya bisa diulang.');
        $this->assertTrue($row->refresh()->canRetry(), 'Kegagalan karena layanan mati harus bisa diulang.');
    }

    public function test_penyimpanan_angka_wajah_yang_gagal_tidak_meninggalkan_berkas(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        $this->faceServiceAnswering([null]);

        $this->upload($admin, $member, ['a.jpg']);

        $row = FacePhotoUpload::query()->firstOrFail();

        // Mesin wajah berhasil membaca wajahnya, tetapi penulisan angkanya ke
        // basis data gagal. Berkas fotonya sudah dipindahkan ke penyimpanan
        // sementara, jadi berkas itu harus ikut dibuang supaya tidak ada berkas
        // yatim yang tidak dipakai siapa pun.
        FaceEmbedding::creating(static function (): void {
            throw new RuntimeException('penyimpanan angka wajah gagal');
        });

        try {
            $this->work($row);
            $this->fail('Penyimpanan yang gagal seharusnya melempar galat.');
        } catch (RuntimeException $error) {
            $this->assertSame('penyimpanan angka wajah gagal', $error->getMessage());
        }

        // Pekerja antrean yang gagal menutup barisnya dan membuang berkasnya.
        (new EncodeFacePhoto((int) $row->getKey()))->failed(new RuntimeException('penyimpanan angka wajah gagal'));

        $this->assertSame(0, FaceEmbedding::query()->count());
        $this->assertSame(FacePhotoUpload::StatusGagal, $row->refresh()->status);
        $this->assertSame([], Storage::disk('local')->allFiles(), 'Berkas yatim tidak boleh tertinggal.');
    }

    public function test_pekerjaan_yang_mati_di_tengah_dibersihkan_bersama_berkasnya(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        $this->faceServiceAnswering([null]);

        $this->upload($admin, $member, ['a.jpg']);

        $row = FacePhotoUpload::query()->firstOrFail();

        // Pekerja antrean yang dimatikan paksa meninggalkan baris "sedang
        // diproses" tanpa akhir riwayat. Tanpa pembersihan, berkasnya menumpuk
        // selamanya karena tidak ada yang pernah menyelesaikannya.
        $row->forceFill([
            'status' => FacePhotoUpload::StatusDiproses,
            'started_at' => now()->subDays(30),
        ])->save();

        $hasil = app(FacePhotoQueueService::class)->purge(7);

        $this->assertSame(1, $hasil['rows']);
        $this->assertSame(1, $hasil['files']);
        $this->assertSame(0, FacePhotoUpload::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    /*
    |--------------------------------------------------------------------------
    | B. Kotak centang penggantian
    |--------------------------------------------------------------------------
    */

    public function test_tanpa_centang_foto_baru_ditambahkan_ke_foto_lama(): void
    {
        $admin = $this->admin();
        $member = $this->member();

        $this->seedFaces($member, ['lama-1', 'lama-2', 'lama-3', 'lama-4']);

        $this->faceServiceAnswering([null, null]);
        $this->upload($admin, $member, ['baru-1.jpg', 'baru-2.jpg']);
        $this->workAll();

        $this->assertSame(6, $member->faceEmbeddings()->count());
        $this->assertSame(
            ['lama-1', 'lama-2', 'lama-3', 'lama-4', 'baru-1.jpg', 'baru-2.jpg'],
            $this->labels($member),
        );
    }

    public function test_dengan_centang_seluruh_foto_baru_yang_berhasil_menggantikan_foto_lama(): void
    {
        $admin = $this->admin();
        $member = $this->member();

        $this->seedFaces($member, ['lama-1', 'lama-2', 'lama-3', 'lama-4']);

        // Enam foto baru: empat berhasil, dua gagal.
        $this->faceServiceAnswering([
            null,
            null,
            FaceEncodingService::StatusNoFace,
            null,
            FaceEncodingService::StatusFaceTooSmall,
            null,
        ]);

        $this->upload($admin, $member, ['baru-1.jpg', 'baru-2.jpg', 'baru-3.jpg', 'baru-4.jpg', 'baru-5.jpg', 'baru-6.jpg'], replace: true);
        $this->workAll();

        $this->assertSame(4, $member->faceEmbeddings()->count(), 'Bukan hanya satu foto yang disisakan.');
        $this->assertSame(
            ['baru-1.jpg', 'baru-2.jpg', 'baru-4.jpg', 'baru-6.jpg'],
            $this->labels($member),
            'Seluruh foto baru yang berhasil dipertahankan, foto lama dibuang, foto gagal tidak ikut.',
        );

        $batch = $this->batch($member);

        $this->assertSame('replaced', $batch['replacement']);
        $this->assertStringContainsString('diganti dengan seluruh foto baru', $batch['note']);
        $this->assertSame(4, $batch['saved']);
        $this->assertSame(2, $batch['failed']);
    }

    public function test_penggantian_dengan_dua_foto_baru_menyisakan_keduanya(): void
    {
        $admin = $this->admin();
        $member = $this->member();

        $this->seedFaces($member, ['lama-1', 'lama-2', 'lama-3', 'lama-4']);

        $this->faceServiceAnswering([null, null]);
        $this->upload($admin, $member, ['baru-1.jpg', 'baru-2.jpg'], replace: true);
        $this->workAll();

        $this->assertSame(['baru-1.jpg', 'baru-2.jpg'], $this->labels($member));
    }

    public function test_semua_foto_baru_gagal_maka_foto_lama_dipertahankan(): void
    {
        $admin = $this->admin();
        $member = $this->member();

        $this->seedFaces($member, ['lama-1', 'lama-2', 'lama-3', 'lama-4']);

        $this->faceServiceAnswering([
            FaceEncodingService::StatusNoFace,
            FaceEncodingService::StatusFaceTooSmall,
            FaceEncodingService::StatusInvalid,
        ]);

        $this->upload($admin, $member, ['baru-1.jpg', 'baru-2.jpg', 'baru-3.jpg'], replace: true);
        $this->workAll();

        $this->assertSame(4, $member->faceEmbeddings()->count());
        $this->assertSame(['lama-1', 'lama-2', 'lama-3', 'lama-4'], $this->labels($member));

        $batch = $this->batch($member);

        $this->assertSame('skipped', $batch['replacement']);
        $this->assertStringContainsString('foto lama tetap dipakai', $batch['note']);
    }

    public function test_penggantian_menunggu_seluruh_foto_kelompok_selesai(): void
    {
        $admin = $this->admin();
        $member = $this->member();

        $this->seedFaces($member, ['lama-1', 'lama-2']);

        $this->faceServiceAnswering([null, null]);
        $rows = $this->upload($admin, $member, ['baru-1.jpg', 'baru-2.jpg'], replace: true);

        $this->work($rows[0]);

        $this->assertSame(3, $member->faceEmbeddings()->count(), 'Foto lama belum dibuang sebelum seluruh kelompok selesai.');

        $this->work($rows[1]);

        $this->assertSame(['baru-1.jpg', 'baru-2.jpg'], $this->labels($member));
    }

    public function test_penggantian_yang_tidak_menghasilkan_foto_baru_tidak_membuang_foto_lama(): void
    {
        $admin = $this->admin();
        $member = $this->member();

        $this->seedFaces($member, ['lama-1', 'lama-2', 'lama-3', 'lama-4']);

        // Hanya foto kedua yang sampai menanyakan mesin wajah, sebab pekerjaan
        // foto pertama meledak lebih dulu.
        $this->faceServiceAnswering([FaceEncodingService::StatusNoFace]);

        $rows = $this->upload($admin, $member, ['baru-1.jpg', 'baru-2.jpg'], replace: true);

        (new EncodeFacePhoto((int) $rows[0]->getKey()))->failed(new RuntimeException('gangguan uji'));

        $this->assertSame(4, $member->faceEmbeddings()->count(), 'Belum ada foto baru yang tersimpan, jadi data lama utuh.');

        $this->work($rows[1]);

        $this->assertSame(
            ['lama-1', 'lama-2', 'lama-3', 'lama-4'],
            $this->labels($member),
            'Tidak ada foto baru yang berhasil, jadi foto lama tetap dipakai.',
        );
    }

    public function test_penggantian_tetap_jalan_walau_satu_pekerjaan_meledak(): void
    {
        $admin = $this->admin();
        $member = $this->member();

        $this->seedFaces($member, ['lama-1', 'lama-2']);

        $this->faceServiceAnswering([null]);
        $rows = $this->upload($admin, $member, ['baru-1.jpg', 'baru-2.jpg'], replace: true);

        $this->work($rows[0]);
        (new EncodeFacePhoto((int) $rows[1]->getKey()))->failed(new RuntimeException('gangguan uji'));

        // Satu foto baru berhasil, jadi kumpulan lama diganti foto baru itu:
        // itulah aturan penggantian yang dipakai aplikasi.
        $this->assertSame(['baru-1.jpg'], $this->labels($member));
    }

    public function test_foto_baru_yang_gagal_tidak_tersimpan_saat_mengganti(): void
    {
        $admin = $this->admin();
        $member = $this->member();

        $this->seedFaces($member, ['lama-1']);

        $this->faceServiceAnswering([null, FaceEncodingService::StatusNoFace]);
        $this->upload($admin, $member, ['baru-1.jpg', 'baru-2.jpg'], replace: true);
        $this->workAll();

        $this->assertSame(['baru-1.jpg'], $this->labels($member));
        $this->assertNotContains('baru-2.jpg', $this->labels($member));
        $this->assertNotContains('lama-1', $this->labels($member));
    }

    public function test_penggantian_hanya_dikerjakan_sekali_untuk_seluruh_kumpulan(): void
    {
        $admin = $this->admin();
        $member = $this->member();

        $this->seedFaces($member, ['lama-1', 'lama-2']);

        $this->faceServiceAnswering([null, null, null]);
        $rows = $this->upload($admin, $member, ['baru-1.jpg', 'baru-2.jpg', 'baru-3.jpg'], replace: true);
        $this->workAll();

        $this->assertSame(['baru-1.jpg', 'baru-2.jpg', 'baru-3.jpg'], $this->labels($member));

        // Menjalankan penyelesaian sekali lagi tidak boleh menghapus foto baru.
        $queue = app(FacePhotoQueueService::class);
        $hasil = $queue->finaliseReplacement($rows[2]->refresh(), app(FaceEnrollmentService::class));

        $this->assertTrue($hasil['done']);
        $this->assertSame(0, $hasil['removed']);
        $this->assertSame(['baru-1.jpg', 'baru-2.jpg', 'baru-3.jpg'], $this->labels($member));
    }

    /*
    |--------------------------------------------------------------------------
    | C. Angka wajah lama tidak boleh hilang karena kiriman alat yang gagal
    |--------------------------------------------------------------------------
    */

    public function test_kiriman_alat_yang_gagal_tidak_menghapus_wajah_lama(): void
    {
        $member = $this->member();
        $admin = $this->admin();

        $this->activeDevice();
        $this->seedFaces($member, ['lama-1', 'lama-2']);

        $this->fakeFaceService(faceCount: 0);

        $this->actingAs($admin)->post(
            route('api.face.register'),
            [
                'device_id' => 'kamera-wajah',
                'identifier_number' => '25001',
                'photo' => $this->photo('alat.jpg'),
                'replace' => true,
            ],
            ['Accept' => 'application/json'],
        )->assertStatus(422)->assertJsonPath('status', 'no_face');

        $this->assertSame(2, $member->faceEmbeddings()->count(), 'Foto lama tetap ada.');
        $this->assertSame(['lama-1', 'lama-2'], $this->labels($member));
    }

    public function test_kiriman_alat_yang_berhasil_memang_mengganti_wajah_lama(): void
    {
        $member = $this->member();
        $admin = $this->admin();

        $this->activeDevice();
        $this->seedFaces($member, ['lama-1', 'lama-2']);

        $this->fakeFaceService();

        $this->actingAs($admin)->post(
            route('api.face.register'),
            [
                'device_id' => 'kamera-wajah',
                'identifier_number' => '25001',
                'photo' => $this->photo('alat.jpg'),
                'replace' => true,
            ],
            ['Accept' => 'application/json'],
        )->assertStatus(201);

        $this->assertSame(1, $member->faceEmbeddings()->count());
    }

    public function test_angka_wajah_alat_yang_bukan_angka_ditolak(): void
    {
        $member = $this->member();
        $admin = $this->admin();

        $this->activeDevice();

        $this->actingAs($admin)->postJson(route('api.face.register'), [
            'device_id' => 'kamera-wajah',
            'identifier_number' => '25001',
            'encoding' => array_fill(0, FaceEmbedding::Dimensions, 'bukan-angka'),
        ])->assertStatus(422);

        $this->assertSame(0, $member->faceEmbeddings()->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Pembantu
    |--------------------------------------------------------------------------
    */

    /**
     * Perangkat kamera yang sudah didaftarkan dan dinyalakan, syarat wajib
     * sebelum API pendaftaran wajah mau menerima kiriman.
     */
    private function activeDevice(): Device
    {
        return Device::create([
            'device_id' => 'kamera-wajah',
            'name' => 'Gerbang Depan',
            'type' => Device::TypeGerbang,
            'status' => Device::StatusAktif,
        ]);
    }

    /**
     * Siapkan angka wajah lama langsung di basis data.
     *
     * @param  array<int, string>  $labels
     */
    private function seedFaces(User $member, array $labels): void
    {
        $faces = app(FaceEnrollmentService::class);

        foreach ($labels as $label) {
            $faces->store($member, array_fill(0, FaceEmbedding::Dimensions, 0.02), $label);
        }
    }

    /**
     * Ringkasan satu kelompok unggahan angggota ini.
     *
     * @return array<string, mixed>
     */
    private function batch(User $member): array
    {
        $service = app(FacePhotoQueueService::class);

        foreach ($service->batches() as $batch) {
            if ($batch['identifier'] === $member->identifier_number) {
                return $batch;
            }
        }

        $this->fail('Kelompok unggahan anggota ini tidak ditemukan di panel kemajuan.');
    }
}
