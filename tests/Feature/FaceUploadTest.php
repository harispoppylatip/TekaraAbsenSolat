<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\FaceEmbedding;
use App\Models\FacePhotoUpload;
use App\Models\User;
use App\Services\FaceEnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FaceUploadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Foto yang menunggu diproses ditulis ke cakram. Cakram ditipu supaya
     * pengujian tidak menumpuk berkas di folder penyimpanan sungguhan.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function admin(): User
    {
        return $this->makeUser(User::RoleAdmin);
    }

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
     * Foto palsu yang benar-benar punya isi berkas. create() menghasilkan berkas
     * kosong sehingga layanan wajah selalu menjawab "foto tidak terbaca".
     */
    private function photo(string $name = 'wajah.jpg'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, 'isi potret uji');
    }

    public function test_unggah_tiga_foto_masuk_antrean_lalu_tersimpan(): void
    {
        $admin = $this->admin();
        $member = $this->makeUser(User::RoleMurid);
        $this->fakeFaceService();

        $this->actingAs($admin)
            ->post(route('face.photos.store'), [
                'user_id' => $member->id,
                'photos' => [$this->photo('a.jpg'), $this->photo('b.jpg'), $this->photo('c.jpg')],
            ])
            ->assertRedirect()
            ->assertSessionHas('status', '3 foto wajah '.$member->name.' berhasil diunggah dan masuk antrean pemrosesan.'
                .' Kemajuannya bisa dilihat pada panel Proses pendaftaran wajah.');

        $this->assertSame(3, FacePhotoUpload::query()->count());
        $this->assertSame(3, FacePhotoUpload::query()->where('status', FacePhotoUpload::StatusBerhasil)->count());
        $this->assertSame(3, FaceEmbedding::query()->count());
        $this->assertSame(3, $member->faceEmbeddings()->count());
    }

    public function test_unggah_lebih_dari_sepuluh_foto_ditolak(): void
    {
        $admin = $this->admin();
        $member = $this->makeUser(User::RoleMurid);
        $this->fakeFaceService();

        $photos = [];

        for ($i = 1; $i <= 11; $i++) {
            $photos[] = $this->photo('f'.$i.'.jpg');
        }

        $this->actingAs($admin)
            ->post(route('face.photos.store'), [
                'user_id' => $member->id,
                'photos' => $photos,
            ])
            ->assertSessionHasErrors([
                'photos' => 'Sekali kirim paling banyak 10 foto.',
            ]);

        $this->assertSame(0, FaceEmbedding::query()->count());
        $this->assertSame(0, FacePhotoUpload::query()->count());
    }

    public function test_foto_tanpa_wajah_ditandai_gagal(): void
    {
        $admin = $this->admin();
        $member = $this->makeUser(User::RoleMurid);
        $this->fakeFaceService(faceCount: 0);

        $this->actingAs($admin)
            ->post(route('face.photos.store'), [
                'user_id' => $member->id,
                'photos' => [$this->photo()],
            ])
            ->assertRedirect()
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'masuk antrean pemrosesan'));

        $this->assertSame(0, FaceEmbedding::query()->count());

        $row = FacePhotoUpload::query()->firstOrFail();

        $this->assertSame(FacePhotoUpload::StatusGagal, $row->status);
        $this->assertSame('Wajah tidak terlihat. Dekatkan wajah ke kamera dan pastikan cahaya cukup.', $row->message);
        $this->assertFalse($row->canRetry());
    }

    public function test_foto_dengan_dua_wajah_ditandai_gagal(): void
    {
        $admin = $this->admin();
        $member = $this->makeUser(User::RoleMurid);
        $this->fakeFaceService(faceCount: 2);

        $this->actingAs($admin)
            ->post(route('face.photos.store'), [
                'user_id' => $member->id,
                'photos' => [$this->photo()],
            ])
            ->assertRedirect()
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'masuk antrean pemrosesan'));

        $this->assertSame(0, FaceEmbedding::query()->count());

        $row = FacePhotoUpload::query()->firstOrFail();

        $this->assertSame(FacePhotoUpload::StatusGagal, $row->status);
        $this->assertStringContainsString('lebih dari satu wajah', (string) $row->message);
    }

    public function test_layanan_wajah_yang_mati_membuat_unggahan_gagal_dan_foto_disimpan(): void
    {
        $admin = $this->admin();
        $member = $this->makeUser(User::RoleMurid);
        $this->fakeFaceService(available: false);

        $this->actingAs($admin)
            ->post(route('face.photos.store'), [
                'user_id' => $member->id,
                'photos' => [$this->photo()],
            ])
            ->assertRedirect()
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'masuk antrean pemrosesan'));

        $this->assertSame(0, FaceEmbedding::query()->count());

        $row = FacePhotoUpload::query()->firstOrFail();

        $this->assertSame(FacePhotoUpload::StatusGagal, $row->status);
        $this->assertStringContainsString('Layanan wajah di server belum aktif', (string) $row->message);
        $this->assertTrue($row->canRetry(), 'Foto yang gagal karena layanan mati harus bisa diulang.');
        $this->assertTrue(Storage::disk('local')->exists($row->path));
    }

    public function test_impor_berkas_menolak_nomor_induk_yang_tidak_dikenal(): void
    {
        $admin = $this->admin();
        $this->fakeFaceService();

        $this->actingAs($admin)
            ->postJson(route('face.photos.upload'), ['photo' => $this->photo(), 'folder' => '9999999'])
            ->assertOk()
            ->assertJson([
                'queued' => false,
                'message' => 'Tidak ada anggota bernomor induk 9999999.',
            ]);
    }

    public function test_impor_berkas_memakai_nama_folder_sebagai_nomor_induk(): void
    {
        $admin = $this->admin();
        $member = $this->makeUser(User::RoleMurid, ['identifier_number' => '2005221']);
        $this->fakeFaceService();
        $batch = (string) Str::uuid();

        $this->actingAs($admin)
            ->postJson(route('face.photos.upload'), [
                'photo' => $this->photo(),
                'folder' => '2005221',
                'batch_id' => $batch,
            ])
            ->assertOk()
            ->assertJson(['queued' => true, 'total' => 1]);

        $this->assertSame(1, $member->faceEmbeddings()->count());

        $row = FacePhotoUpload::query()->firstOrFail();

        $this->assertSame($batch, $row->batch_id);
        $this->assertSame(FacePhotoUpload::StatusBerhasil, $row->status);
    }

    public function test_batas_dua_puluh_foto_membuang_yang_paling_lama(): void
    {
        $admin = $this->admin();
        $member = $this->makeUser(User::RoleMurid);
        $this->activeDevice();
        $this->fakeFaceService();

        $faces = app(FaceEnrollmentService::class);

        for ($i = 1; $i <= 20; $i++) {
            $faces->store($member, array_fill(0, FaceEmbedding::Dimensions, 0.01), 'Uji '.$i);
        }

        $oldest = $member->faceEmbeddings()->orderBy('id')->value('id');

        $this->actingAs($admin)
            ->postJson(route('api.face.register'), [
                'device_id' => 'kamera-wajah',
                'identifier_number' => $member->identifier_number,
                'encoding' => array_fill(0, FaceEmbedding::Dimensions, 0.02),
            ])
            ->assertStatus(201)
            ->assertJson([
                'status' => 'saved',
                'display_message' => 'Wajah tersimpan',
                'total' => 20,
            ])
            ->assertJsonPath('message', 'Satu wajah '.$member->name.' tersimpan.');

        $this->assertSame(20, $member->faceEmbeddings()->count());
        $this->assertSame(0, $member->faceEmbeddings()->whereKey($oldest)->count());
    }

    public function test_unggahan_berlebih_menyebut_foto_terlama_dihapus(): void
    {
        $admin = $this->admin();
        $member = $this->makeUser(User::RoleMurid);
        $this->fakeFaceService();

        $faces = app(FaceEnrollmentService::class);

        for ($i = 1; $i <= 20; $i++) {
            $faces->store($member, array_fill(0, FaceEmbedding::Dimensions, 0.01), 'Uji '.$i);
        }

        $oldest = $member->faceEmbeddings()->orderBy('id')->value('id');

        $this->actingAs($admin)
            ->post(route('face.photos.store'), [
                'user_id' => $member->id,
                'photos' => [$this->photo('ke-21.jpg')],
            ])
            ->assertRedirect()
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'masuk antrean pemrosesan'));

        $this->assertSame(20, $member->faceEmbeddings()->count());
        $this->assertSame(0, $member->faceEmbeddings()->whereKey($oldest)->count());

        $row = FacePhotoUpload::query()->firstOrFail();

        $this->assertStringContainsString(
            'Foto terlama dihapus karena batas 20 foto per anggota.',
            (string) $row->message,
        );
    }

    public function test_perangkat_mendaftarkan_wajah_dari_angka_wajah(): void
    {
        $this->activeDevice();
        $member = $this->makeUser(User::RoleMurid, ['identifier_number' => '2005221']);
        $this->fakeFaceService();

        $this->postJson(route('api.face.register'), [
            'device_id' => 'kamera-wajah',
            'identifier_number' => '2005221',
            'encoding' => array_fill(0, FaceEmbedding::Dimensions, 0.03),
        ])
            ->assertStatus(201)
            ->assertJson([
                'ok' => true,
                'blocked' => false,
                'status' => 'saved',
                'display_message' => 'Wajah tersimpan',
                'display_detail' => $member->name,
                'member' => ['identifier_number' => '2005221'],
                'total' => 1,
            ]);

        $row = FaceEmbedding::query()->firstOrFail();

        $this->assertSame($member->id, $row->user_id);
        $this->assertSame('Kamera Gerbang Depan', $row->label);
        $this->assertSame(FaceEmbedding::DefaultModel, $row->model);
    }

    public function test_perangkat_mendaftarkan_wajah_menolak_nomor_induk_yang_tidak_dikenal(): void
    {
        $this->activeDevice();
        $this->fakeFaceService();

        $this->postJson(route('api.face.register'), [
            'device_id' => 'kamera-wajah',
            'identifier_number' => '9999999',
            'encoding' => array_fill(0, FaceEmbedding::Dimensions, 0.03),
        ])
            ->assertStatus(422)
            ->assertJson([
                'blocked' => true,
                'status' => 'unknown_member',
                'display_message' => 'Nomor tak dikenal',
            ]);

        $this->assertSame(0, FaceEmbedding::query()->count());
    }

    public function test_perangkat_yang_dimatikan_belum_boleh_mendaftarkan_wajah(): void
    {
        Device::create([
            'device_id' => 'kamera-wajah',
            'name' => 'Gerbang Depan',
            'type' => Device::TypeGerbang,
            'status' => Device::StatusNonaktif,
        ]);

        $member = $this->makeUser(User::RoleMurid, ['identifier_number' => '2005221']);
        $this->fakeFaceService();

        $this->postJson(route('api.face.register'), [
            'device_id' => 'kamera-wajah',
            'identifier_number' => '2005221',
            'encoding' => array_fill(0, FaceEmbedding::Dimensions, 0.03),
        ])
            ->assertStatus(403)
            ->assertJson(['status' => 'device_inactive', 'display_message' => 'Sensor nonaktif']);

        $this->assertSame(0, $member->faceEmbeddings()->count());
    }

    public function test_alat_mengambil_daftar_angka_wajah(): void
    {
        $this->activeDevice();
        $member = $this->makeUser(User::RoleMurid, ['identifier_number' => '2005221', 'class_name' => 'X TKJ 1']);
        $this->fakeFaceService();

        FaceEmbedding::create([
            'user_id' => $member->id,
            'label' => 'Kamera Gerbang Depan',
            'model' => FaceEmbedding::DefaultModel,
            'embedding' => array_fill(0, FaceEmbedding::Dimensions, 0.04),
        ]);

        $response = $this->getJson(route('api.face.embeddings', ['device_id' => 'kamera-wajah']))
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'status' => 'ok',
                'dimensions' => FaceEmbedding::Dimensions,
                'display_message' => 'Daftar wajah',
                'display_detail' => '1 wajah',
                'total' => 1,
            ]);

        $this->assertEqualsWithDelta((float) config('face.tolerance'), $response->json('tolerance'), 0.0001);
        $this->assertSame('2005221', $response->json('embeddings.0.identifier_number'));
        $this->assertCount(FaceEmbedding::Dimensions, $response->json('embeddings.0.embedding'));
    }

    public function test_hapus_satu_foto_dan_hapus_semua_foto_anggota(): void
    {
        $admin = $this->admin();
        $member = $this->makeUser(User::RoleMurid);
        $this->fakeFaceService();

        $first = FaceEmbedding::create([
            'user_id' => $member->id,
            'label' => 'Uji 1',
            'model' => FaceEmbedding::DefaultModel,
            'embedding' => array_fill(0, FaceEmbedding::Dimensions, 0.05),
        ]);

        FaceEmbedding::create([
            'user_id' => $member->id,
            'label' => 'Uji 2',
            'model' => FaceEmbedding::DefaultModel,
            'embedding' => array_fill(0, FaceEmbedding::Dimensions, 0.06),
        ]);

        $this->actingAs($admin)
            ->delete(route('face.photos.destroy', $first))
            ->assertRedirect()
            ->assertSessionHas('status', 'Satu foto wajah '.$member->name.' dihapus.');

        $this->assertSame(1, $member->faceEmbeddings()->count());

        $this->actingAs($admin)
            ->delete(route('face.member.destroy', $member))
            ->assertRedirect()
            ->assertSessionHas('status', '1 foto wajah '.$member->name.' dihapus. Anggota dan riwayat absennya tetap ada.');

        $this->assertSame(0, $member->faceEmbeddings()->count());
    }

    public function test_menugaskan_dan_menarik_petugas_kamera(): void
    {
        $admin = $this->admin();
        $petugas = $this->makeUser(User::RolePetugas);

        $this->actingAs($admin)
            ->put(route('face.operator.update', $petugas), ['assigned' => 1])
            ->assertRedirect()
            ->assertSessionHas('status', $petugas->name.' sekarang boleh memakai kamera absen.');

        $this->assertTrue($petugas->refresh()->can_scan_face);

        $this->actingAs($admin)
            ->put(route('face.operator.update', $petugas), ['assigned' => 0])
            ->assertRedirect();

        $this->assertFalse($petugas->refresh()->can_scan_face);
    }

    public function test_murid_tidak_bisa_ditugaskan_memegang_kamera(): void
    {
        $admin = $this->admin();
        $murid = $this->makeUser(User::RoleMurid);
        $this->fakeFaceService();

        $this->actingAs($admin)
            ->put(route('face.operator.update', $murid), ['assigned' => 1])
            ->assertRedirect()
            ->assertSessionHas('error', 'Anggota berperan murid tidak bisa ditugaskan memegang kamera absen.');

        $this->assertFalse($murid->refresh()->can_scan_face);
    }

    public function test_halaman_wajah_menampilkan_kartu_anggota(): void
    {
        $admin = $this->admin();
        $member = $this->makeUser(User::RoleMurid);
        $this->fakeFaceService();

        FaceEmbedding::create([
            'user_id' => $member->id,
            'label' => 'Uji 1',
            'model' => FaceEmbedding::DefaultModel,
            'embedding' => array_fill(0, FaceEmbedding::Dimensions, 0.07),
        ]);

        $this->actingAs($admin)
            ->get(route('face.index'))
            ->assertOk()
            ->assertSee($member->name)
            ->assertSee('1 foto wajah tersimpan')
            ->assertSee('Wajah terdaftar');
    }

    /**
     * Isian unggah diletakkan di luar wilayah yang menyegarkan dirinya sendiri.
     * Kalau tidak, pilihan berkas dan nomor induk yang sedang diisi admin
     * terhapus sendiri setiap wilayah itu disegarkan.
     */
    public function test_isian_unggah_berada_di_luar_wilayah_penyegaran(): void
    {
        $admin = $this->admin();

        $html = $this->actingAs($admin)
            ->get(route('face.index'))
            ->assertOk()
            ->getContent();

        $start = strpos($html, 'data-live="wajah"');

        $this->assertNotFalse($start);

        $region = substr($html, (int) $start);

        $this->assertStringContainsString('data-bulk-upload', $html);
        $this->assertStringNotContainsString('data-bulk-upload', $region);
        $this->assertStringNotContainsString('name="photos[]"', $region);
    }

    /**
     * Tiga cara menambah foto wajah terbuka sesuai pilihan admin, bukan
     * berjajar penuh sejak halaman dibuka.
     */
    public function test_tiga_panel_alat_tampil_tertutup(): void
    {
        $admin = $this->admin();

        $html = $this->actingAs($admin)
            ->get(route('face.index'))
            ->assertOk()
            ->getContent();

        foreach (['tool-unggah', 'tool-folder', 'tool-satu'] as $record) {
            $tag = $this->panelTag($html, $record);

            $this->assertNotSame('', $tag, 'Panel '.$record.' tidak ditemukan.');
            $this->assertStringNotContainsString('open', $tag, 'Panel '.$record.' terbuka sejak awal.');
        }
    }

    /**
     * Galat isian harus terbaca. Panel yang tertutup membuka dirinya sendiri
     * saat ada galat, jadi pesannya tidak bersembunyi di balik lipatan.
     */
    public function test_galat_unggah_membuka_panel_unggah(): void
    {
        $admin = $this->admin();

        $html = $this->actingAs($admin)
            ->followingRedirects()
            ->from(route('face.index'))
            ->post(route('face.photos.store'), [])
            ->assertOk()
            ->assertSee('Pilih anggota yang wajahnya didaftarkan.')
            ->assertSee('Pilih foto wajah lebih dulu.')
            ->getContent();

        $this->assertStringContainsString('open', $this->panelTag($html, 'tool-unggah'));
    }

    /**
     * Mengambil tag pembuka <details> milik satu panel alat supaya sifat
     * tutup atau bukanya bisa diperiksa tanpa terikat spasi atribut.
     */
    private function panelTag(string $html, string $record): string
    {
        preg_match('/<details[^>]*data-record="'.preg_quote($record, '/').'"[^>]*>/', $html, $matches);

        return $matches[0] ?? '';
    }
}
