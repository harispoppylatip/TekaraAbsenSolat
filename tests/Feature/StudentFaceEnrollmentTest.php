<?php

namespace Tests\Feature;

use App\Models\FaceEmbedding;
use App\Models\FacePhotoUpload;
use App\Models\User;
use App\Services\FaceEncodingService;
use App\Services\FaceEnrollmentService;
use App\Services\FacePhotoQueueService;
use App\Services\SolatSessionService;
use App\Services\StudentEnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pendaftaran wajah mandiri oleh pemilik akun.
 *
 * Pengujiannya berjalan dengan antrean langsung (QUEUE_CONNECTION=sync), jadi
 * setelah satu kiriman foto kembali, seluruh pekerjaannya sudah selesai dan
 * keadaannya bisa diperiksa apa adanya. Mesin wajah Python selalu ditiru, sebab
 * yang diuji di sini adalah aturan aplikasinya, bukan ketelitian modelnya.
 */
class StudentFaceEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private function photo(string $name = 'wajah.jpg'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, 'isi potret uji '.$name);
    }

    /**
     * Angka wajah berbeda untuk setiap foto, supaya tidak ada foto yang saling
     * dianggap kembar.
     *
     * @return array<int, array<int, float>>
     */
    private function beragam(int $jumlah): array
    {
        $hasil = [];

        for ($i = 0; $i < $jumlah; $i++) {
            $vektor = array_fill(0, FaceEmbedding::Dimensions, 0.0);
            $vektor[$i % FaceEmbedding::Dimensions] = 1.0;
            $hasil[] = $vektor;
        }

        return $hasil;
    }

    /**
     * Konfigurasi bawaan: tiga foto wajib, jadi angka pada pengujian dibuat
     * mengikuti pengaturan yang sedang berlaku.
     */
    private function wajib(): int
    {
        return app(StudentEnrollmentService::class)->requiredPhotos();
    }

    public function test_mengganti_sandi_mengarahkan_ke_pendaftaran_wajah(): void
    {
        $murid = $this->makeUser(User::RoleMurid, [
            'password' => '260010',
            'password_changed_at' => null,
        ]);

        $this->actingAs($murid)
            ->put(route('password.update'), [
                'current_password' => '260010',
                'password' => 'SandiBaru2026!',
                'password_confirmation' => 'SandiBaru2026!',
            ])
            ->assertRedirect(route('enrollment.show'))
            ->assertSessionHas('status');

        $this->actingAs($murid->refresh())
            ->get(route('enrollment.show'))
            ->assertOk()
            ->assertSee('Cara mengambil foto yang benar');
    }

    public function test_pemilik_yang_sudah_terdaftar_tidak_diarahkan_ke_pendaftaran(): void
    {
        $murid = $this->makeUser(User::RoleMurid, [
            'password' => '260011',
            'password_changed_at' => null,
            'enrollment_status' => User::EnrollmentCompleted,
        ]);

        $this->actingAs($murid)
            ->put(route('password.update'), [
                'current_password' => '260011',
                'password' => 'SandiBaru2026!',
                'password_confirmation' => 'SandiBaru2026!',
            ])
            ->assertRedirect(route('murid.riwayat'));
    }

    public function test_halaman_pendaftaran_menampilkan_identitas_dari_akun_bukan_dari_isian(): void
    {
        $murid = $this->makeUser(User::RoleMurid, ['name' => 'Andi Pratama', 'class_name' => 'X TKJ 1']);

        $this->actingAs($murid)
            ->get(route('enrollment.show'))
            ->assertOk()
            ->assertSee('Andi Pratama')
            ->assertSee($murid->identifier_number)
            ->assertSee('X TKJ 1')
            ->assertSee('Belum mendaftar')
            ->assertDontSee('name="member_id"')
            ->assertDontSee('name="user_id"');
    }

    public function test_guru_dan_petugas_juga_bisa_mendaftar_wajah(): void
    {
        foreach ([User::RolePetugas, User::RoleAdmin] as $peran) {
            $akun = $this->makeUser($peran);

            $this->actingAs($akun)
                ->get(route('enrollment.show'))
                ->assertOk()
                ->assertSee('Pendaftaran Wajah');
        }
    }

    public function test_satu_foto_yang_lolos_membuat_keadaan_berjalan(): void
    {
        Storage::fake('local');
        $this->fakeFaceService(faces: [], sequence: $this->beragam(5));

        $murid = $this->makeUser(User::RoleMurid);

        $this->actingAs($murid)
            ->post(route('enrollment.photos.store'), ['photos' => [$this->photo('satu.jpg')]])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(1, $murid->refresh()->faceEmbeddings()->count());
        $this->assertSame(User::EnrollmentInProgress, $murid->enrollmentStatus());
    }

    public function test_foto_terkumpul_sampai_batas_membuat_keadaan_selesai(): void
    {
        Storage::fake('local');
        $wajib = $this->wajib();
        $this->fakeFaceService(faces: [], sequence: $this->beragam($wajib + 2));

        $murid = $this->makeUser(User::RoleMurid);
        $perSubmit = app(StudentEnrollmentService::class)->maxPerSubmit();

        $foto = [];
        for ($i = 0; $i < $wajib; $i++) {
            $foto[] = $this->photo('foto-'.$i.'.jpg');
        }

        // Dikirim dalam beberapa kelompok, sebab batas sekali kirim lebih kecil
        // daripada jumlah wajibnya.
        foreach (array_chunk($foto, $perSubmit) as $kelompok) {
            $this->actingAs($murid)
                ->post(route('enrollment.photos.store'), ['photos' => $kelompok])
                ->assertRedirect();
        }

        $murid->refresh();

        $this->assertSame($wajib, $murid->faceEmbeddings()->count());
        $this->assertSame(User::EnrollmentCompleted, $murid->enrollmentStatus());
        $this->assertNotNull($murid->enrolled_at);
    }

    public function test_halaman_konfirmasi_hanya_untuk_yang_sudah_selesai(): void
    {
        $murid = $this->makeUser(User::RoleMurid);

        $this->actingAs($murid)
            ->get(route('enrollment.done'))
            ->assertRedirect(route('enrollment.show'));

        $murid->forceFill(['enrollment_status' => User::EnrollmentCompleted])->save();

        $this->actingAs($murid)
            ->get(route('enrollment.done'))
            ->assertOk()
            ->assertSee('Yang disimpan');
    }

    public function test_menyelesaikan_pendaftaran_ditolak_bila_foto_belum_cukup(): void
    {
        $murid = $this->makeUser(User::RoleMurid);

        $this->actingAs($murid)
            ->post(route('enrollment.complete'))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(User::EnrollmentNotStarted, $murid->refresh()->enrollmentStatus());
    }

    public function test_foto_tanpa_wajah_ditolak_dengan_alasan_yang_terbaca(): void
    {
        Storage::fake('local');
        $this->fakeFaceService(faceCount: 0);

        $murid = $this->makeUser(User::RoleMurid);

        $this->actingAs($murid)
            ->post(route('enrollment.photos.store'), ['photos' => [$this->photo()]])
            ->assertRedirect();

        $this->followLiveRefresh($murid);

        $this->assertSame(0, $murid->refresh()->faceEmbeddings()->count());
        $this->assertSame(User::EnrollmentRejected, $murid->enrollmentStatus());
        $this->assertStringContainsString(
            'Wajah tidak terlihat',
            (string) FacePhotoUpload::query()->latest('id')->value('message'),
        );
    }

    public function test_foto_dengan_dua_wajah_ditolak(): void
    {
        Storage::fake('local');
        $this->fakeFaceService(faceCount: 2);

        $murid = $this->makeUser(User::RoleMurid);

        $this->actingAs($murid)
            ->post(route('enrollment.photos.store'), ['photos' => [$this->photo()]])
            ->assertRedirect();

        $this->assertSame(0, $murid->refresh()->faceEmbeddings()->count());
        $this->assertSame(
            FaceEnrollmentService::StatusMultipleFaces,
            FacePhotoUpload::query()->latest('id')->value('result_status'),
        );
    }

    public function test_foto_gelap_buram_dan_terlalu_kecil_ditolak_dengan_alasan_masing_masing(): void
    {
        Storage::fake('local');
        $murid = $this->makeUser(User::RoleMurid);

        foreach ([
            FaceEncodingService::StatusTooDark => FaceEnrollmentService::StatusTooDark,
            FaceEncodingService::StatusBlurry => FaceEnrollmentService::StatusBlurry,
            FaceEncodingService::StatusBadPose => FaceEnrollmentService::StatusBadPose,
        ] as $dariMesin => $diharapkan) {
            $this->fakeFaceService(status: $dariMesin);

            $this->actingAs($murid)
                ->post(route('enrollment.photos.store'), ['photos' => [$this->photo($dariMesin.'.jpg')]])
                ->assertRedirect();

            $this->assertSame(
                $diharapkan,
                FacePhotoUpload::query()->latest('id')->value('result_status'),
                'Keadaan foto '.$dariMesin.' tidak sesuai.',
            );
        }

        $this->assertSame(0, $murid->refresh()->faceEmbeddings()->count());
    }

    public function test_wajah_yang_terlalu_kecil_ditolak(): void
    {
        Storage::fake('local');
        $this->fakeFaceService(faceSize: 10);

        $murid = $this->makeUser(User::RoleMurid);

        $this->actingAs($murid)
            ->post(route('enrollment.photos.store'), ['photos' => [$this->photo()]])
            ->assertRedirect();

        $this->assertSame(0, $murid->refresh()->faceEmbeddings()->count());
        $this->assertSame(
            FaceEnrollmentService::StatusFaceTooSmall,
            FacePhotoUpload::query()->latest('id')->value('result_status'),
        );
    }

    public function test_foto_kembar_ditolak_tanpa_menolak_foto_yang_berbeda(): void
    {
        Storage::fake('local');
        $this->fakeFaceService(faces: [], sequence: $this->beragam(4));

        $murid = $this->makeUser(User::RoleMurid);

        // Kelompok pertama: tiga foto berbeda, seluruhnya harus diterima.
        $this->actingAs($murid)
            ->post(route('enrollment.photos.store'), [
                'photos' => [$this->photo('a.jpg'), $this->photo('b.jpg'), $this->photo('c.jpg')],
            ])
            ->assertRedirect();

        $this->assertSame(3, $murid->refresh()->faceEmbeddings()->count());

        // Kelompok kedua: angka wajahnya sama semua, jadi foto kedua dan ketiga
        // harus ditolak sebagai foto kembar.
        $sama = array_fill(0, FaceEmbedding::Dimensions, 0.01);
        $this->fakeFaceService(encoding: $sama);

        $this->actingAs($murid)
            ->post(route('enrollment.photos.store'), [
                'photos' => [$this->photo('d.jpg'), $this->photo('e.jpg'), $this->photo('f.jpg')],
            ])
            ->assertRedirect();

        $this->assertSame(4, $murid->refresh()->faceEmbeddings()->count());
        $this->assertSame(
            2,
            FacePhotoUpload::query()
                ->where('result_status', FaceEnrollmentService::StatusDuplicate)
                ->count(),
        );
        $this->assertStringContainsString(
            'hampir sama',
            (string) FacePhotoUpload::query()
                ->where('result_status', FaceEnrollmentService::StatusDuplicate)
                ->value('message'),
        );
    }

    public function test_kiriman_melebihi_batas_sekali_kirim_ditolak_sebelum_berkas_disimpan(): void
    {
        Storage::fake('local');
        $this->fakeFaceService();

        $murid = $this->makeUser(User::RoleMurid);
        $batas = app(StudentEnrollmentService::class)->maxPerSubmit();

        $foto = [];
        for ($i = 0; $i <= $batas; $i++) {
            $foto[] = $this->photo('ke-'.$i.'.jpg');
        }

        $this->actingAs($murid)
            ->post(route('enrollment.photos.store'), ['photos' => $foto])
            ->assertSessionHasErrors('photos');

        // Tidak satu pun berkas berpindah ke penyimpanan.
        $this->assertSame(0, FacePhotoUpload::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles(FacePhotoQueueService::Folder));
    }

    public function test_kiriman_melebihi_batas_jumlah_foto_akun_ditolak(): void
    {
        Storage::fake('local');
        $this->fakeFaceService();

        $murid = $this->makeUser(User::RoleMurid);

        foreach (range(1, (int) config('face.enrollment.max_photos', 10)) as $i) {
            FaceEmbedding::create([
                'user_id' => $murid->id,
                'embedding' => array_fill(0, FaceEmbedding::Dimensions, 0.0),
                'model' => (string) config('face.model'),
            ]);
        }

        $this->actingAs($murid)
            ->post(route('enrollment.photos.store'), ['photos' => [$this->photo()]])
            ->assertSessionHasErrors('photos');
    }

    public function test_layanan_wajah_mati_memberi_pesan_yang_jelas_dan_menyisakan_berkas_untuk_diulang(): void
    {
        Storage::fake('local');
        $this->fakeFaceService(available: false);

        $murid = $this->makeUser(User::RoleMurid);

        $this->actingAs($murid)
            ->post(route('enrollment.photos.store'), ['photos' => [$this->photo()]])
            ->assertRedirect();

        $row = FacePhotoUpload::query()->latest('id')->firstOrFail();

        $this->assertSame(FacePhotoUpload::StatusGagal, $row->status);
        $this->assertSame(FaceEnrollmentService::StatusUnavailable, $row->result_status);
        $this->assertStringContainsString('belum aktif', (string) $row->message);

        // Berkasnya sengaja dipertahankan supaya bisa dicoba ulang setelah
        // layanan wajah dinyalakan.
        Storage::disk('local')->assertExists($row->path);
        $this->assertTrue($row->canRetry());
    }

    public function test_pemilik_yang_belum_terdaftar_melihat_pemberitahuan_antrean_yang_belum_jalan(): void
    {
        Storage::fake('local');

        $murid = $this->makeUser(User::RoleMurid);

        // Baris antrean dibuat langsung supaya tidak diproses pekerjaannya,
        // yaitu keadaan yang terjadi saat pekerja antrean di server mati.
        // Waktunya dimundurkan lebih dari batas tunggu supaya terhitung
        // menggantung.
        $row = new FacePhotoUpload;
        $row->forceFill([
            'batch_id' => 'uji-belum-jalan',
            'user_id' => $murid->id,
            'original_name' => 'a.jpg',
            'path' => FacePhotoQueueService::Folder.'/a.jpg',
            'label' => 'a.jpg',
            'self_enrolled' => true,
            'status' => FacePhotoUpload::StatusMenunggu,
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ])->save();

        $this->actingAs($murid)
            ->get(route('enrollment.show'))
            ->assertOk()
            ->assertSee('Kemajuan pemeriksaan foto')
            // Kalimatnya diambil sebagian saja, sebab kalimat penuh di berkas
            // tampilan terbelah menjadi beberapa baris dan hasilnya di halaman
            // ikut terbelah. Membandingkan kalimat penuh akan gagal walaupun
            // halamannya benar.
            ->assertSee('Pemeriksaan foto belum dijalankan di server');
    }

    /**
     * Laporan kelompok yang sudah selesai tetap tampil di halaman pendaftaran.
     *
     * Kejadian nyata yang ditemukan saat pengujian di peramban: panel admin
     * menghilangkan kartu kelompok yang selesai setelah dua puluh detik supaya
     * panelnya tidak penuh kartu lama. Aturan itu terbawa ke halaman pendaftaran
     * dan akibatnya pemilik akun hampir selalu menemukan panelnya kosong hanya
     * beberapa detik setelah mengirim, sehingga alasan fotonya ditolak tidak
     * pernah terbaca.
     */
    public function test_laporan_foto_tetap_terbaca_walau_pemeriksaannya_sudah_lama(): void
    {
        Storage::fake('local');

        $murid = $this->makeUser(User::RoleMurid);
        $lama = now()->subHour();

        $row = new FacePhotoUpload;
        $row->forceFill([
            'batch_id' => 'uji-sudah-lama',
            'user_id' => $murid->id,
            'original_name' => 'gelap.jpg',
            'path' => FacePhotoQueueService::Folder.'/gelap.jpg',
            'label' => 'gelap.jpg',
            'self_enrolled' => true,
            'status' => FacePhotoUpload::StatusGagal,
            'result_status' => FaceEnrollmentService::StatusTooDark,
            'message' => 'Fotonya terlalu gelap. Cari tempat yang lebih terang, lalu ambil ulang.',
            'created_at' => $lama,
            'updated_at' => $lama,
            'finished_at' => $lama,
        ])->save();

        // Panel admin memang menyembunyikannya.
        $this->assertSame([], app(FacePhotoQueueService::class)->batches());

        $this->actingAs($murid)
            ->get(route('enrollment.show'))
            ->assertOk()
            ->assertSee('gelap.jpg')
            ->assertSee('terlalu gelap');
    }

    public function test_menu_pendaftaran_tetap_tampil_selagi_foto_diperiksa(): void
    {
        $murid = $this->makeUser(User::RoleMurid, ['enrollment_status' => User::EnrollmentProcessing]);

        $this->actingAs($murid)
            ->get(route('enrollment.show'))
            ->assertOk()
            ->assertSee('Daftarkan Wajah');
    }

    public function test_kiriman_kedua_ditolak_selagi_yang_pertama_masih_diproses(): void
    {
        Storage::fake('local');

        $murid = $this->makeUser(User::RoleMurid);

        FacePhotoUpload::create([
            'batch_id' => 'uji-masih-diproses',
            'user_id' => $murid->id,
            'original_name' => 'a.jpg',
            'path' => FacePhotoQueueService::Folder.'/a.jpg',
            'label' => 'a.jpg',
            'self_enrolled' => true,
            'status' => FacePhotoUpload::StatusMenunggu,
        ]);

        $this->actingAs($murid)
            ->post(route('enrollment.photos.store'), ['photos' => [$this->photo()]])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_kemajuan_hanya_memuat_kiriman_milik_sendiri(): void
    {
        Storage::fake('local');

        $murid = $this->makeUser(User::RoleMurid);
        $lain = $this->makeUser(User::RoleMurid, ['name' => 'Anggota Lain']);

        FacePhotoUpload::create([
            'batch_id' => 'milik-orang-lain',
            'user_id' => $lain->id,
            'original_name' => 'orang-lain.jpg',
            'path' => FacePhotoQueueService::Folder.'/lain.jpg',
            'label' => 'orang-lain.jpg',
            'status' => FacePhotoUpload::StatusMenunggu,
        ]);

        $jawaban = $this->actingAs($murid)->getJson(route('enrollment.progress'))->assertOk();

        $this->assertSame([], $jawaban->json('batches'));
    }

    public function test_keluar_di_tengah_pendaftaran_tidak_menghapus_foto_yang_sudah_lolos(): void
    {
        Storage::fake('local');
        $this->fakeFaceService(faces: [], sequence: $this->beragam(3));

        $murid = $this->makeUser(User::RoleMurid);

        $this->actingAs($murid)
            ->post(route('enrollment.photos.store'), ['photos' => [$this->photo()]])
            ->assertRedirect();

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->assertSame(1, $murid->refresh()->faceEmbeddings()->count());

        $this->actingAs($murid)
            ->get(route('enrollment.show'))
            ->assertOk()
            ->assertSee('Belum selesai');
    }

    public function test_foto_milik_akun_lain_tidak_bisa_dihapus(): void
    {
        $murid = $this->makeUser(User::RoleMurid);
        $lain = $this->makeUser(User::RoleMurid);

        $milikLain = FaceEmbedding::create([
            'user_id' => $lain->id,
            'embedding' => array_fill(0, FaceEmbedding::Dimensions, 0.0),
            'model' => (string) config('face.model'),
        ]);

        $this->actingAs($murid)
            ->delete(route('enrollment.photos.destroy', $milikLain))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('face_embeddings', ['id' => $milikLain->id]);
    }

    public function test_anggota_bisa_menghapus_seluruh_fotonya_lalu_mendaftar_ulang(): void
    {
        Storage::fake('local');
        $this->fakeFaceService(faces: [], sequence: $this->beragam(3));

        $murid = $this->makeUser(User::RoleMurid, ['enrollment_status' => User::EnrollmentCompleted]);

        FaceEmbedding::create([
            'user_id' => $murid->id,
            'embedding' => array_fill(0, FaceEmbedding::Dimensions, 0.0),
            'model' => (string) config('face.model'),
        ]);

        $this->actingAs($murid)
            ->delete(route('enrollment.photos.clear'))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(0, $murid->refresh()->faceEmbeddings()->count());
        $this->assertSame(User::EnrollmentNotStarted, $murid->enrollmentStatus());
    }

    public function test_pendaftaran_ulang_menjaga_foto_lama_sampai_foto_baru_lolos(): void
    {
        Storage::fake('local');

        $murid = $this->makeUser(User::RoleMurid);
        $lama = FaceEmbedding::create([
            'user_id' => $murid->id,
            'embedding' => array_fill(0, FaceEmbedding::Dimensions, 0.02),
            'label' => 'foto-lama.jpg',
            'model' => (string) config('face.model'),
        ]);

        // Foto baru gagal: kumpulan lama harus tetap utuh.
        $this->fakeFaceService(faceCount: 0);

        $this->actingAs($murid)
            ->post(route('enrollment.photos.store'), [
                'photos' => [$this->photo('baru.jpg')],
                'replace' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('face_embeddings', ['id' => $lama->id]);

        // Foto baru berhasil: baru sekarang kumpulan lama diganti.
        $this->fakeFaceService(encoding: array_fill(0, FaceEmbedding::Dimensions, 0.9));

        $this->actingAs($murid)
            ->post(route('enrollment.photos.store'), [
                'photos' => [$this->photo('baru-2.jpg')],
                'replace' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('face_embeddings', ['id' => $lama->id]);
        $this->assertSame(1, $murid->refresh()->faceEmbeddings()->count());
    }

    public function test_angka_wajah_dari_model_lain_dihitung_terpisah_dan_meminta_daftar_ulang(): void
    {
        $murid = $this->makeUser(User::RoleMurid);

        // Data model lama tidak bisa dibandingkan dengan angka wajah sekarang.
        FaceEmbedding::create([
            'user_id' => $murid->id,
            'embedding' => array_fill(0, 128, 0.01),
            'model' => 'dlib-resnet-v1',
        ]);

        $this->actingAs($murid)
            ->get(route('enrollment.show'))
            ->assertOk()
            ->assertSee('tidak bisa dipakai');

        $this->assertSame(User::EnrollmentNeedsReenrollment, app(StudentEnrollmentService::class)->refresh($murid->refresh()));
    }

    public function test_tombol_ulangi_hanya_bekerja_untuk_kelompok_miliknya_sendiri(): void
    {
        Storage::fake('local');

        $murid = $this->makeUser(User::RoleMurid);
        $lain = $this->makeUser(User::RoleMurid);

        foreach ([$murid, $lain] as $pemilik) {
            FacePhotoUpload::create([
                'batch_id' => 'kelompok-'.$pemilik->id,
                'user_id' => $pemilik->id,
                'original_name' => 'a.jpg',
                'path' => FacePhotoQueueService::Folder.'/'.$pemilik->id.'.jpg',
                'label' => 'a.jpg',
                'self_enrolled' => true,
                'status' => FacePhotoUpload::StatusGagal,
                'result_status' => FaceEnrollmentService::StatusUnavailable,
                'finished_at' => now(),
            ]);

            Storage::disk('local')->put(FacePhotoQueueService::Folder.'/'.$pemilik->id.'.jpg', 'isi');
        }

        // Nama kelompok milik orang lain tidak boleh bisa dipakai.
        $this->actingAs($murid)
            ->post(route('enrollment.retry', 'kelompok-'.$lain->id))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_angka_wajah_baru_langsung_dipakai_absensi(): void
    {
        Storage::fake('local');
        $this->seedPrayerTimes();

        $murid = $this->makeUser(User::RoleMurid);
        $petugas = $this->makeUser(User::RolePetugas, ['can_scan_face' => true]);

        $angka = array_fill(0, FaceEmbedding::Dimensions, 0.0);
        $angka[0] = 1.0;
        $this->fakeFaceService(encoding: $angka);

        $this->actingAs($murid)
            ->post(route('enrollment.photos.store'), ['photos' => [$this->photo()]])
            ->assertRedirect();

        // Sesi dibuka lewat layanan sesi seperti petugas sungguhan, bukan
        // dengan mengubah kolomnya langsung, supaya keadaannya sama dengan
        // keadaan saat kamera absen dipakai.
        $admin = $this->makeUser(User::RoleAdmin);
        $sesi = $this->makeSession('zuhur');

        $this->travelTo(Carbon::parse('today 12:05'));
        $this->assertTrue(app(SolatSessionService::class)->open($sesi, $admin)['ok']);

        $this->fakeFaceService(encoding: $angka);

        $this->actingAs($petugas)
            ->post(route('attendance.wajah.scan'), ['photo' => UploadedFile::fake()->image('absen.jpg')])
            ->assertOk()
            ->assertJson(['ok' => true])
            ->assertJsonPath('faces.0.status', 'recorded')
            ->assertJsonPath('faces.0.attendance.recorded', true);

        $this->assertDatabaseHas('solat_attendance_logs', [
            'user_id' => $murid->id,
            'solat_session_id' => $sesi->refresh()->id,
        ]);
    }

    public function test_panel_admin_menghitung_keadaan_pendaftaran(): void
    {
        $admin = $this->makeUser(User::RoleAdmin);

        $sudah = $this->makeUser(User::RoleMurid, ['enrollment_status' => User::EnrollmentCompleted]);
        $perlu = $this->makeUser(User::RoleMurid, ['enrollment_status' => User::EnrollmentNeedsReenrollment]);

        FaceEmbedding::create([
            'user_id' => $sudah->id,
            'embedding' => array_fill(0, FaceEmbedding::Dimensions, 0.01),
            'model' => (string) config('face.model'),
        ]);

        $halaman = $this->actingAs($admin)->get(route('face.index'))->assertOk();

        $halaman->assertSee('Sudah terdaftar')
            ->assertSee('Belum terdaftar')
            ->assertSee('Menunggu pendaftaran wajah')
            ->assertSee($perlu->name)
            ->assertSee('Minta daftar ulang')
            ->assertSee('Pendaftaran terakhir');
    }

    public function test_admin_bisa_meminta_anggota_mendaftar_ulang(): void
    {
        $admin = $this->makeUser(User::RoleAdmin);
        $murid = $this->makeUser(User::RoleMurid, ['enrollment_status' => User::EnrollmentCompleted]);

        $this->actingAs($admin)
            ->put(route('face.enrollment.reopen', $murid))
            ->assertRedirect()
            ->assertSessionHas('status');

        $murid->refresh();

        $this->assertSame(User::EnrollmentNeedsReenrollment, $murid->enrollmentStatus());

        // Data lamanya sengaja tidak dihapus supaya pemiliknya masih bisa absen
        // sebelum foto barunya selesai diperiksa.
        $this->assertTrue($murid->needsEnrollment());
    }

    /**
     * Buka halaman pendaftaran supaya pesan pemeriksaan foto bisa dibaca.
     */
    private function followLiveRefresh(User $murid): void
    {
        $this->actingAs($murid)
            ->get(route('enrollment.show'))
            ->assertOk()
            ->assertSee('Kemajuan pemeriksaan foto');
    }
}
