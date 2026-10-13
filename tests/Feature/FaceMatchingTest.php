<?php

namespace Tests\Feature;

use App\Models\FaceEmbedding;
use App\Models\SolatAttendanceLog;
use App\Models\SolatSession;
use App\Models\User;
use App\Services\FaceEncodingService;
use App\Services\FaceMatcherService;
use App\Services\SolatAttendanceService;
use App\Services\SolatSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Siapa yang boleh dicatat sebagai hadir dan siapa yang harus ditolak.
 *
 * Angka wajah di berkas ini dibuat sendiri, bukan dari foto sungguhan: seluruh
 * 512 angka diisi satu nilai yang sama sehingga jaraknya bisa ditentukan persis.
 * Cara ini menguji ATURAN KEPUTUSANNYA, bukan ketajaman mesin wajah. Seberapa
 * akurat mesin wajah di sekolah hanya bisa diketahui dengan foto sungguhan yang
 * diukur terpisah, dan itu tidak dibuktikan oleh berkas ini.
 *
 * Ambang bawaan yang dipakai pengujian: penerimaan 1,15; anggota berfoto
 * tunggal 1,04; selisih minimal juara pertama dan kedua 0,14. Seluruh jarak di
 * berkas ini memakai skala model InsightFace, tempat pengukuran atas 34 foto
 * milik 5 orang menaruh orang berbeda pada jarak 1,1650 sampai 1,5032 dan orang
 * sama pada jarak di bawah 1,1427.
 */
class FaceMatchingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Jarak yang terukur antara dua orang yang berbeda wajahnya pada model
     * InsightFace. Pengukuran atas 34 foto milik 5 orang menghasilkan jarak
     * orang berbeda 1,1650 sampai 1,5032, jadi angka ini berada di tengahnya
     * dan tetap di bawah ambang penerimaan 1,15 milik anggota berfoto banyak.
     * Pada model dlib sebelumnya, orang lain hanya berjarak 0,575 dengan ambang
     * 0,5, dan itulah sebabnya orang lain pernah tercatat sebagai anggota.
     */
    private const JarakOrangLain = 1.3225;

    private function matcher(): FaceMatcherService
    {
        return app(FaceMatcherService::class);
    }

    private function attendance(): SolatAttendanceService
    {
        return app(SolatAttendanceService::class);
    }

    /**
     * Angka wajah buatan pada jarak tertentu dari wajah yang dipindai.
     *
     * @return array<int, float>
     */
    private function encoding(float $distance = 0.0): array
    {
        return array_fill(0, FaceEmbedding::Dimensions, $distance / sqrt(FaceEmbedding::Dimensions));
    }

    /**
     * Wajah yang dipindai: seluruh angkanya nol.
     *
     * @return array<int, float>
     */
    private function probe(): array
    {
        return array_fill(0, FaceEmbedding::Dimensions, 0.0);
    }

    /**
     * Simpan beberapa angka wajah untuk satu anggota.
     *
     * @param  array<int, float>  $distances  jarak setiap foto ke wajah yang dipindai
     */
    private function attach(User $member, array $distances, ?string $model = null): void
    {
        foreach ($distances as $distance) {
            FaceEmbedding::create([
                'user_id' => $member->getKey(),
                'label' => 'Foto uji',
                'model' => $model ?? FaceEmbedding::DefaultModel,
                'embedding' => $this->encoding($distance),
            ]);
        }
    }

    /**
     * Buka sesi zuhur seperti petugas sungguhan.
     */
    private function openZuhur(): SolatSession
    {
        $session = $this->makeSession('zuhur');

        $this->travelTo(Carbon::parse('today 12:05'));
        app(SolatSessionService::class)->open($session, $this->makeUser(User::RoleAdmin));

        return $session->refresh();
    }

    /*
    |--------------------------------------------------------------------------
    | Aturan keputusan pencocokan
    |--------------------------------------------------------------------------
    */

    public function test_anggota_dengan_beberapa_foto_diterima_saat_semua_fotonya_dekat(): void
    {
        $member = $this->makeUser(User::RoleMurid);
        $this->attach($member, [0.69, 0.92]);

        $match = $this->matcher()->match($this->probe());

        $this->assertSame(FaceMatcherService::StatusMatched, $match['status']);
        $this->assertSame($member->id, $match['user']?->id);
        $this->assertEqualsWithDelta(0.805, $match['distance'], 0.001);
        $this->assertSame(2, $match['photos']);
    }

    public function test_jarak_terdekat_saja_tidak_cukup_bila_satu_foto_menyimpang(): void
    {
        $member = $this->makeUser(User::RoleMurid);

        // Satu foto dekat, satu foto jauh. Kalau yang dipakai hanya jarak
        // terdekat, anggota ini lolos dengan 0,69.
        $this->attach($member, [0.69, 2.185]);

        $match = $this->matcher()->match($this->probe());

        $this->assertSame(FaceMatcherService::StatusUnknown, $match['status']);
        $this->assertSame(FaceMatcherService::ReasonAboveTolerance, $match['reason']);
        $this->assertNull($match['user']);
        $this->assertEqualsWithDelta(0.69, $match['closest'], 0.001);
    }

    public function test_anggota_berfoto_tunggal_harus_sangat_dekat(): void
    {
        $member = $this->makeUser(User::RoleMurid);
        $this->attach($member, [1.081]);

        $sempit = $this->matcher()->match($this->probe());

        $this->assertSame(FaceMatcherService::StatusUnknown, $sempit['status']);
        $this->assertSame(FaceMatcherService::ReasonAboveTolerance, $sempit['reason']);
    }

    public function test_anggota_berfoto_tunggal_diterima_bila_jaraknya_dekat(): void
    {
        $member = $this->makeUser(User::RoleMurid);
        $this->attach($member, [0.92]);

        $match = $this->matcher()->match($this->probe());

        $this->assertSame(FaceMatcherService::StatusMatched, $match['status']);
        $this->assertSame(1, $match['photos']);
    }

    public function test_dua_anggota_yang_sama_sama_dekat_dilaporkan_ambigu(): void
    {
        $this->attach($this->makeUser(User::RoleMurid), [0.69, 0.69]);
        $this->attach($this->makeUser(User::RoleMurid), [0.736, 0.736]);

        $match = $this->matcher()->match($this->probe());

        $this->assertSame(FaceMatcherService::StatusAmbiguous, $match['status']);
        $this->assertSame(FaceMatcherService::ReasonTooCloseToAnother, $match['reason']);
        $this->assertNull($match['user']);
        $this->assertNotNull($match['runner_up']);
        $this->assertEqualsWithDelta(0.046, $match['margin'], 0.001);
    }

    public function test_anggota_kedua_yang_jauh_tidak_membuat_hasil_ambigu(): void
    {
        $dekat = $this->makeUser(User::RoleMurid);
        $this->attach($dekat, [0.69, 0.69]);
        $this->attach($this->makeUser(User::RoleMurid), [1.84, 1.84]);

        $match = $this->matcher()->match($this->probe());

        $this->assertSame(FaceMatcherService::StatusMatched, $match['status']);
        $this->assertSame($dekat->id, $match['user']?->id);
        $this->assertEqualsWithDelta(1.15, $match['margin'], 0.001);
    }

    public function test_angka_wajah_yang_dimensinya_salah_ditolak(): void
    {
        $this->attach($this->makeUser(User::RoleMurid), [0.23, 0.23]);

        // Angka 128 ini adalah panjang angka model dlib yang dipakai sebelum
        // pindah ke InsightFace. Setelah pindah, angka seperti ini wajib ditolak
        // supaya sidik wajah lama tidak dibandingkan dengan yang baru.
        $match = $this->matcher()->match(array_fill(0, 128, 0.0));

        $this->assertSame(FaceMatcherService::StatusInvalid, $match['status']);
        $this->assertSame(FaceMatcherService::ReasonWrongDimensions, $match['reason']);
    }

    public function test_belum_ada_data_wajah_menghasilkan_alasan_tidak_ada_pembanding(): void
    {
        $match = $this->matcher()->match($this->probe());

        $this->assertSame(FaceMatcherService::StatusUnknown, $match['status']);
        $this->assertSame(FaceMatcherService::ReasonNoReference, $match['reason']);
    }

    public function test_baris_yang_rusak_dan_bermodel_lain_dilewati(): void
    {
        $member = $this->makeUser(User::RoleMurid);
        $this->attach($member, [0.46, 0.46]);

        // Angka wajah yang jumlahnya bukan 512 dan angka wajah dari model lain
        // tidak boleh ikut dihitung, sebab keduanya tidak setara.
        FaceEmbedding::create([
            'user_id' => $member->getKey(),
            'label' => 'Rusak',
            'model' => FaceEmbedding::DefaultModel,
            'embedding' => [0.1, 0.2, 0.3],
        ]);

        $lain = $this->makeUser(User::RoleMurid);
        $this->attach($lain, [0.0, 0.0], model: 'mesin-lama');

        $match = $this->matcher()->match($this->probe());

        $this->assertSame(FaceMatcherService::StatusMatched, $match['status']);
        $this->assertSame($member->id, $match['user']?->id);
        $this->assertSame(3, $match['skipped']);
    }

    public function test_baris_bermodel_lain_ikut_dihitung_bila_pemeriksaannya_dimatikan(): void
    {
        config(['face.enforce_model' => false]);

        $lain = $this->makeUser(User::RoleMurid);
        $this->attach($lain, [0.23, 0.23], model: 'mesin-lama');

        $match = $this->matcher()->match($this->probe());

        $this->assertSame(FaceMatcherService::StatusMatched, $match['status']);
        $this->assertSame($lain->id, $match['user']?->id);
    }

    public function test_bentuk_lama_pencarian_anggota_masih_bekerja(): void
    {
        $member = $this->makeUser(User::RoleMurid);
        $this->attach($member, [0.46, 0.46]);

        $lama = $this->matcher()->identify($this->probe());

        $this->assertSame($member->id, $lama['user']->id);
        $this->assertEqualsWithDelta(0.46, $lama['distance'], 0.001);

        $this->assertNull($this->matcher()->identify(array_fill(0, FaceEmbedding::Dimensions, 9.0)));
    }

    public function test_foto_yang_menyimpang_dari_foto_lain_ditandai(): void
    {
        $member = $this->makeUser(User::RoleMurid);
        $this->attach($member, [0.0, 0.0, 2.07]);

        $member->load('faceEmbeddings');

        $jarak = $this->matcher()->siblingDistances($member->faceEmbeddings);

        $this->assertCount(3, $jarak);
        $this->assertEqualsWithDelta(2.07, max($jarak), 0.001);
        $this->assertSame(1, count(array_filter($jarak, fn (float $nilai): bool => $nilai > $this->matcher()->tolerance())));
    }

    /*
    |--------------------------------------------------------------------------
    | Akibatnya pada catatan kehadiran
    |--------------------------------------------------------------------------
    */

    public function test_wajah_orang_lain_tidak_tercatat_sebagai_anggota(): void
    {
        $this->seedPrayerTimes();

        $haris = $this->makeUser(User::RoleMurid, ['name' => 'Haris']);
        $this->attach($haris, [self::JarakOrangLain, self::JarakOrangLain + 0.023]);

        $this->openZuhur();

        // Orang lain berdiri di depan kamera: jaraknya ke seluruh foto Haris
        // persis seperti yang terukur di mesin sekolah.
        $this->fakeFaceService($this->probe());

        $hasil = $this->attendance()->recordFromImage('potret', 'kamera-wajah', Carbon::now());

        $this->assertFalse($hasil['ok']);
        $this->assertSame(SolatAttendanceService::StatusUnknown, $hasil['status']);
        $this->assertSame('Belum terdaftar', $hasil['display_message']);
        $this->assertSame(0, SolatAttendanceLog::query()->count());
    }

    public function test_wajah_yang_memang_cocok_tetap_tercatat(): void
    {
        $this->seedPrayerTimes();

        $haris = $this->makeUser(User::RoleMurid, ['name' => 'Haris']);
        $this->attach($haris, [0.92, 0.966]);

        $this->openZuhur();
        $this->fakeFaceService($this->probe());

        $hasil = $this->attendance()->recordFromImage('potret', 'kamera-wajah', Carbon::now());

        $this->assertTrue($hasil['ok']);
        $this->assertSame(SolatAttendanceService::StatusRecorded, $hasil['status']);

        $log = SolatAttendanceLog::query()->firstOrFail();

        $this->assertSame($haris->id, $log->user_id);
        $this->assertEqualsWithDelta(0.943, $log->match_distance, 0.001);
    }

    public function test_absen_tidak_tersimpan_saat_hasil_pencocokan_ambigu(): void
    {
        $this->seedPrayerTimes();

        $this->attach($this->makeUser(User::RoleMurid), [0.69, 0.69]);
        $this->attach($this->makeUser(User::RoleMurid), [0.713, 0.713]);

        $this->openZuhur();
        $this->fakeFaceService($this->probe());

        $hasil = $this->attendance()->recordFromImage('potret', 'kamera-wajah', Carbon::now());

        $this->assertFalse($hasil['ok']);
        $this->assertSame(SolatAttendanceService::StatusAmbiguous, $hasil['status']);
        $this->assertSame('Wajah ambigu', $hasil['display_message']);
        $this->assertSame(0, SolatAttendanceLog::query()->count());
    }

    public function test_absen_tidak_tersimpan_saat_angka_wajah_dari_model_lain(): void
    {
        $this->seedPrayerTimes();

        $member = $this->makeUser(User::RoleMurid);
        $this->attach($member, [0.115, 0.115], model: 'mesin-lama');

        $this->openZuhur();
        $this->fakeFaceService($this->probe());

        $hasil = $this->attendance()->recordFromImage('potret', 'kamera-wajah', Carbon::now());

        $this->assertFalse($hasil['ok']);
        $this->assertSame(SolatAttendanceService::StatusUnknown, $hasil['status']);
        $this->assertSame(0, SolatAttendanceLog::query()->count());
    }

    public function test_balasan_banyak_wajah_dari_mesin_wajah_ditolak(): void
    {
        $this->seedPrayerTimes();

        $this->attach($this->makeUser(User::RoleMurid), [0.23, 0.23]);
        $this->openZuhur();

        $this->fakeFaceService(status: FaceEncodingService::StatusMultipleFaces, faceCount: 2);

        $hasil = $this->attendance()->recordFromImage('potret', 'kamera-wajah', Carbon::now());

        $this->assertFalse($hasil['ok']);
        $this->assertSame(SolatAttendanceService::StatusCrowded, $hasil['status']);
        $this->assertSame('Satu wajah saja', $hasil['display_message']);
        $this->assertSame(0, SolatAttendanceLog::query()->count());
    }

    public function test_balasan_wajah_terlalu_kecil_ditolak(): void
    {
        $this->seedPrayerTimes();

        $this->attach($this->makeUser(User::RoleMurid), [0.23, 0.23]);
        $this->openZuhur();

        $this->fakeFaceService(status: FaceEncodingService::StatusFaceTooSmall, faceSize: 24);

        $hasil = $this->attendance()->recordFromImage('potret', 'kamera-wajah', Carbon::now());

        $this->assertFalse($hasil['ok']);
        $this->assertSame(SolatAttendanceService::StatusFaceTooSmall, $hasil['status']);
        $this->assertSame('Dekatkan wajah', $hasil['display_message']);
        $this->assertSame(0, SolatAttendanceLog::query()->count());
    }

    public function test_pesan_layar_perangkat_tetap_muat_enam_belas_kolom(): void
    {
        $this->seedPrayerTimes();

        $this->attach($this->makeUser(User::RoleMurid), [0.69, 0.69]);
        $this->attach($this->makeUser(User::RoleMurid), [0.713, 0.713]);

        $this->openZuhur();
        $this->fakeFaceService($this->probe());

        $hasil = $this->attendance()->recordFromImage('potret', 'kamera-wajah', Carbon::now());

        foreach (['display_message', 'display_detail'] as $kunci) {
            $this->assertLessThanOrEqual(16, mb_strlen((string) $hasil[$kunci]), $kunci.' terlalu panjang untuk layar perangkat.');
        }
    }
}
