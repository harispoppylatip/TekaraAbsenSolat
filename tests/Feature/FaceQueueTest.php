<?php

namespace Tests\Feature;

use App\Jobs\EncodeFacePhoto;
use App\Models\FaceEmbedding;
use App\Models\FacePhotoUpload;
use App\Models\User;
use App\Services\FaceEnrollmentService;
use App\Services\FacePhotoQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Antrean pengolahan foto wajah: halaman admin harus dijawab seketika,
 * sedangkan pengubahan foto menjadi angka wajah dikerjakan di latar belakang
 * dan kemajuannya bisa dipantau.
 */
class FaceQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function admin(): User
    {
        return $this->makeUser(User::RoleAdmin);
    }

    private function photo(string $name = 'wajah.jpg'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, 'isi potret uji');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function staged(User $member, array $attributes = []): FacePhotoUpload
    {
        $path = FacePhotoQueueService::Folder.'/uji-'.Str::uuid().'.jpg';

        Storage::disk('local')->put($path, 'isi potret uji');

        return FacePhotoUpload::create(array_merge([
            'batch_id' => (string) Str::uuid(),
            'user_id' => $member->getKey(),
            'original_name' => 'wajah.jpg',
            'path' => $path,
            'label' => 'wajah.jpg',
            'replace' => false,
            'status' => FacePhotoUpload::StatusMenunggu,
        ], $attributes));
    }

    private function work(FacePhotoUpload $row): void
    {
        (new EncodeFacePhoto((int) $row->getKey()))->handle(
            app(FaceEnrollmentService::class),
            app(FacePhotoQueueService::class),
        );
    }

    public function test_unggahan_dijawab_seketika_dan_pekerjaannya_masuk_antrean(): void
    {
        Queue::fake();

        $admin = $this->admin();
        $member = $this->makeUser(User::RoleMurid);

        $this->actingAs($admin)
            ->post(route('face.photos.store'), [
                'user_id' => $member->id,
                'photos' => [$this->photo('a.jpg'), $this->photo('b.jpg')],
            ])
            ->assertRedirect()
            ->assertSessionHas('status', '2 foto wajah '.$member->name.' berhasil diunggah dan masuk antrean pemrosesan.'
                .' Kemajuannya bisa dilihat pada panel Proses pendaftaran wajah.');

        $this->assertSame(2, FacePhotoUpload::query()->count());
        $this->assertSame(0, FaceEmbedding::query()->count(), 'Pekerjaan berat tidak boleh berjalan di dalam permintaan web.');
        $this->assertSame(1, (int) FacePhotoUpload::query()->distinct()->count('batch_id'), 'Sekali kirim memakai satu kelompok.');

        Queue::assertPushed(EncodeFacePhoto::class, 2);

        FacePhotoUpload::query()->each(function (FacePhotoUpload $row): void {
            $this->assertTrue(Storage::disk('local')->exists($row->path));
        });
    }

    public function test_pekerjaan_menyimpan_angka_wajah_lalu_membuang_foto(): void
    {
        $member = $this->makeUser(User::RoleMurid);
        $this->fakeFaceService();

        $row = $this->staged($member);
        $path = $row->path;

        $this->work($row);
        $row->refresh();

        $this->assertSame(FacePhotoUpload::StatusBerhasil, $row->status);
        $this->assertSame(FaceEnrollmentService::StatusSaved, $row->result_status);
        $this->assertNotNull($row->face_embedding_id);
        $this->assertNotNull($row->finished_at);
        $this->assertSame(1, $member->faceEmbeddings()->count());
        $this->assertTrue($member->faceEmbeddings()->whereKey($row->face_embedding_id)->exists());
        $this->assertFalse(Storage::disk('local')->exists($path), 'Foto mentah dibuang setelah angkanya tersimpan.');
    }

    public function test_pekerjaan_menyisakan_foto_saat_layanan_wajah_mati(): void
    {
        $member = $this->makeUser(User::RoleMurid);
        $this->fakeFaceService(available: false);

        $row = $this->staged($member);

        $this->work($row);
        $row->refresh();

        $this->assertSame(FacePhotoUpload::StatusGagal, $row->status);
        $this->assertSame(FaceEnrollmentService::StatusUnavailable, $row->result_status);
        $this->assertSame(0, $member->faceEmbeddings()->count());
        $this->assertTrue($row->canRetry(), 'Foto yang gagal karena layanan mati harus bisa diulang.');
        $this->assertTrue(Storage::disk('local')->exists($row->path), 'Fotonya bagus, jadi jangan dibuang.');
    }

    public function test_pekerjaan_tidak_dijalankan_dua_kali(): void
    {
        $member = $this->makeUser(User::RoleMurid);
        $this->fakeFaceService();

        $row = $this->staged($member);

        $this->work($row);
        $this->work($row->refresh());

        $this->assertSame(1, $member->faceEmbeddings()->count());
    }

    public function test_pekerjaan_aman_saat_catatan_antreannya_sudah_hilang(): void
    {
        $member = $this->makeUser(User::RoleMurid);
        $this->fakeFaceService();

        $row = $this->staged($member);
        $row->delete();

        $this->work($row);

        $this->assertSame(0, $member->faceEmbeddings()->count());
    }

    public function test_ganti_foto_membuang_foto_lama_setelah_satu_berhasil(): void
    {
        $member = $this->makeUser(User::RoleMurid);
        $this->fakeFaceService();

        $faces = app(FaceEnrollmentService::class);

        $faces->store($member, array_fill(0, FaceEmbedding::Dimensions, 0.01), 'Lama 1');
        $faces->store($member, array_fill(0, FaceEmbedding::Dimensions, 0.01), 'Lama 2');

        $this->assertSame(2, $member->faceEmbeddings()->count());

        $row = $this->staged($member, ['replace' => true]);

        $this->work($row);
        $row->refresh();

        $this->assertSame(FacePhotoUpload::StatusBerhasil, $row->status);
        $this->assertSame(1, $member->faceEmbeddings()->count(), 'Foto lama dibuang setelah foto barunya tersimpan.');
    }

    public function test_halaman_menampilkan_panel_kemajuan(): void
    {
        $admin = $this->admin();
        $member = $this->makeUser(User::RoleMurid, ['identifier_number' => '2005221']);

        $this->staged($member, [
            'status' => FacePhotoUpload::StatusDiproses,
            'started_at' => now()->subSeconds(6),
        ]);

        $this->actingAs($admin)
            ->get(route('face.index'))
            ->assertOk()
            ->assertSee('Proses pendaftaran wajah')
            ->assertSee('Nomor induk 2005221')
            ->assertSee('Sedang berjalan, perkiraan')
            ->assertSee('data-face-progress', false);
    }

    public function test_jawaban_kemajuan_memuat_persen_dan_sisa_pekerjaan(): void
    {
        $admin = $this->admin();
        $member = $this->makeUser(User::RoleMurid);

        $this->actingAs($admin)
            ->getJson(route('face.progress'))
            ->assertOk()
            ->assertJsonPath('unfinished', false)
            ->assertJsonPath('batches', []);

        $first = $this->staged($member);
        $this->staged($member, ['batch_id' => $first->batch_id]);

        $this->actingAs($admin)
            ->getJson(route('face.progress'))
            ->assertOk()
            ->assertJsonPath('unfinished', true)
            ->assertJsonPath('batches.0.member', $member->name)
            ->assertJsonPath('batches.0.total', 2)
            ->assertJsonPath('batches.0.percent', 0)
            ->assertJsonPath('batches.0.done', false)
            ->assertJsonPath('batches.0.rows.0.note', 'Menunggu antrean');

        $this->fakeFaceService();

        FacePhotoUpload::query()->get()->each(fn (FacePhotoUpload $row) => $this->work($row));

        $this->actingAs($admin)
            ->getJson(route('face.progress'))
            ->assertOk()
            ->assertJsonPath('unfinished', false)
            ->assertJsonPath('batches.0.percent', 100)
            ->assertJsonPath('batches.0.saved', 2)
            ->assertJsonPath('batches.0.retryable', 0)
            ->assertJsonPath('batches.0.note', 'Selesai. 2 foto tersimpan.');
    }

    public function test_persen_berjalan_naik_di_bawah_seratus(): void
    {
        $admin = $this->admin();
        $member = $this->makeUser(User::RoleMurid);

        $this->staged($member, [
            'status' => FacePhotoUpload::StatusDiproses,
            'started_at' => now()->subSeconds(7),
        ]);

        $response = $this->actingAs($admin)->getJson(route('face.progress'))->assertOk();

        $percent = (int) $response->json('batches.0.percent');

        $this->assertGreaterThanOrEqual(35, $percent);
        $this->assertLessThan(100, $percent, 'Angka penuh hanya setelah angkanya benar-benar tersimpan.');
    }

    public function test_kartu_kelompok_yang_selesai_hilang_setelah_waktu_tunggu(): void
    {
        $admin = $this->admin();
        $member = $this->makeUser(User::RoleMurid);
        $this->fakeFaceService();

        $row = $this->staged($member);
        $this->work($row);

        $row->forceFill(['finished_at' => now()->subSeconds(5)])->save();

        $this->actingAs($admin)
            ->get(route('face.index'))
            ->assertOk()
            ->assertSee($row->batch_id);

        $this->actingAs($admin)
            ->getJson(route('face.progress'))
            ->assertOk()
            ->assertJsonPath('batches.0.saved', 1)
            ->assertJsonPath('batches.0.remove_at', fn ($moment) => is_string($moment) && $moment !== '');

        $row->forceFill(['finished_at' => now()->subSeconds(FacePhotoQueueService::DoneLingerSeconds + 1)])->save();

        $this->actingAs($admin)
            ->getJson(route('face.progress'))
            ->assertOk()
            ->assertJsonCount(0, 'batches');

        $this->actingAs($admin)
            ->get(route('face.index'))
            ->assertOk()
            ->assertDontSee($row->batch_id);
    }

    public function test_kartu_bergalat_tetap_tampil_selama_masih_bisa_diulang(): void
    {
        $admin = $this->admin();
        $member = $this->makeUser(User::RoleMurid);

        $row = $this->staged($member, [
            'status' => FacePhotoUpload::StatusGagal,
            'result_status' => FaceEnrollmentService::StatusUnavailable,
            'message' => 'Layanan wajah di server belum aktif.',
            'started_at' => now()->subMinute(),
            'finished_at' => now()->subSeconds(FacePhotoQueueService::DoneLingerSeconds + 30),
        ]);

        $this->actingAs($admin)
            ->getJson(route('face.progress'))
            ->assertOk()
            ->assertJsonCount(1, 'batches')
            ->assertJsonPath('batches.0.retryable', 1)
            ->assertJsonPath('batches.0.remove_at', null);

        Storage::disk('local')->delete($row->path);

        $this->actingAs($admin)
            ->getJson(route('face.progress'))
            ->assertOk()
            ->assertJsonCount(0, 'batches');
    }

    public function test_antrean_yang_tidak_dijalankan_diberi_peringatan(): void
    {
        $admin = $this->admin();
        $member = $this->makeUser(User::RoleMurid);

        $row = $this->staged($member);
        $row->forceFill(['created_at' => now()->subMinutes(5)])->save();

        $this->actingAs($admin)
            ->get(route('face.index'))
            ->assertOk()
            ->assertSee('php artisan queue:work');

        $this->actingAs($admin)
            ->getJson(route('face.progress'))
            ->assertOk()
            ->assertJsonPath('batches.0.stalled', true);
    }

    /**
     * Peringatan antrean mati diletakkan di ATAS daftar foto. Kalau ditaruh di
     * bawah, sembilan baris foto mendorongnya ke luar layar dan admin hanya
     * melihat kemajuan 0% tanpa tahu sebabnya.
     */
    public function test_peringatan_antrean_mati_berada_di_atas_daftar_foto(): void
    {
        $admin = $this->admin();
        $member = $this->makeUser(User::RoleMurid);

        $row = $this->staged($member);
        $row->forceFill(['created_at' => now()->subMinutes(5)])->save();

        $halaman = $this->actingAs($admin)
            ->get(route('face.index'))
            ->assertOk()
            ->getContent();

        $peringatan = strpos($halaman, 'data-batch-alert');
        $kepala = strpos($halaman, 'queue-head');
        $catatan = strpos($halaman, 'data-batch-note');
        $daftar = strpos($halaman, 'queue-rows');

        $this->assertIsInt($peringatan);
        $this->assertIsInt($kepala);
        $this->assertIsInt($catatan);
        $this->assertIsInt($daftar);

        $this->assertGreaterThan($kepala, $peringatan, 'Peringatan harus sesudah kepala kartu.');
        $this->assertLessThan($catatan, $peringatan, 'Peringatan harus mendahului catatan kemajuan.');
        $this->assertLessThan($daftar, $peringatan, 'Peringatan harus mendahului daftar foto.');
    }

    public function test_ulangi_mengembalikan_foto_yang_gagal_ke_antrean(): void
    {
        Queue::fake();

        $admin = $this->admin();
        $member = $this->makeUser(User::RoleMurid);

        $row = $this->staged($member, [
            'status' => FacePhotoUpload::StatusGagal,
            'result_status' => FaceEnrollmentService::StatusUnavailable,
            'message' => 'Layanan wajah di server belum aktif.',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('face.queue.retry', $row->batch_id))
            ->assertRedirect()
            ->assertSessionHas('status', '1 foto dimasukkan kembali ke antrean pemrosesan.');

        $row->refresh();

        $this->assertSame(FacePhotoUpload::StatusMenunggu, $row->status);
        $this->assertNull($row->message);
        $this->assertNull($row->started_at);
        $this->assertNull($row->finished_at);

        Queue::assertPushed(EncodeFacePhoto::class, 1);
    }

    public function test_ulangi_menolak_ketika_berkasnya_sudah_dibuang(): void
    {
        Queue::fake();

        $admin = $this->admin();
        $member = $this->makeUser(User::RoleMurid);

        $row = $this->staged($member, [
            'status' => FacePhotoUpload::StatusGagal,
            'result_status' => FaceEnrollmentService::StatusNoFace,
            'message' => 'Wajah tidak terlihat.',
            'finished_at' => now(),
        ]);

        Storage::disk('local')->delete($row->path);

        $this->actingAs($admin)
            ->post(route('face.queue.retry', $row->batch_id))
            ->assertRedirect()
            ->assertSessionHas('error', 'Tidak ada foto yang bisa diulang. Foto yang gagal karena isinya tidak terbaca harus diunggah ulang.');

        Queue::assertNothingPushed();
    }

    public function test_pembersihan_membuang_catatan_lama_dan_menyisakan_yang_sedang_diproses(): void
    {
        $member = $this->makeUser(User::RoleMurid);

        $old = $this->staged($member, [
            'status' => FacePhotoUpload::StatusBerhasil,
            'result_status' => FaceEnrollmentService::StatusSaved,
            'finished_at' => now()->subDays(10),
        ]);

        $running = $this->staged($member, [
            'status' => FacePhotoUpload::StatusDiproses,
            'started_at' => now(),
        ]);

        $report = app(FacePhotoQueueService::class)->purge(7);

        $this->assertSame(['rows' => 1, 'files' => 1], $report);
        $this->assertNull(FacePhotoUpload::query()->find($old->getKey()));
        $this->assertNotNull(FacePhotoUpload::query()->find($running->getKey()));
        $this->assertFalse(Storage::disk('local')->exists($old->path));
        $this->assertTrue(Storage::disk('local')->exists($running->path));
    }

    public function test_perintah_pembersihan_berjalan(): void
    {
        $member = $this->makeUser(User::RoleMurid);

        $this->staged($member, [
            'status' => FacePhotoUpload::StatusBerhasil,
            'finished_at' => now()->subDays(10),
        ]);

        $this->artisan('face:purge-uploads')->assertSuccessful();

        $this->assertSame(0, FacePhotoUpload::query()->count());
    }
}
