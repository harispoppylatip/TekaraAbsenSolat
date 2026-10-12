<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Keadaan tugas petugas kamera absen.
 *
 * Satu-satunya sumber kebenaran penugasan adalah kolom `can_scan_face` pada
 * data pengguna, dan kolom itu pula yang dipakai penjaga hak akses halaman
 * kamera. Pengujian ini mengunci dua hal sekaligus: apa yang tertulis di
 * halaman Data Wajah harus sama dengan keadaan data sungguhan, dan sama dengan
 * apakah akun itu benar benar boleh masuk ke halaman kamera.
 */
class FaceOperatorStatusTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = $this->makeUser(User::RoleAdmin);

        // Halaman Data Wajah menanyakan keadaan mesin wajah. Sambungannya
        // dipalsukan supaya pengujian tidak menunggu jaringan dan tidak
        // bergantung pada mesin wajah yang sedang menyala atau tidak.
        $this->fakeFaceService();

        return $admin;
    }

    private function petugas(string $name = 'Nur Hidayah', bool $assigned = false): User
    {
        return $this->makeUser(User::RolePetugas, ['name' => $name, 'can_scan_face' => $assigned]);
    }

    /**
     * Halaman Data Wajah, dimuat seperti admin membukanya.
     */
    private function page(?User $actor = null): TestResponse
    {
        return $this->actingAs($actor ?? $this->admin())->get(route('face.index'));
    }

    /**
     * Tugaskan atau cabut tugas, lalu ikuti pengalihannya kembali ke halaman.
     */
    private function toggle(User $member, bool $assigned, ?User $actor = null): TestResponse
    {
        return $this->actingAs($actor ?? $this->admin())
            ->from(route('face.index'))
            ->put(route('face.operator.update', $member), ['assigned' => $assigned ? 1 : 0]);
    }

    /*
    |--------------------------------------------------------------------------
    | Keadaan tugas di halaman
    |--------------------------------------------------------------------------
    */

    public function test_akun_yang_belum_ditugaskan_berstatus_tidak_bertugas(): void
    {
        $petugas = $this->petugas();
        $this->fakeFaceService();

        $this->page()
            ->assertOk()
            ->assertSee($petugas->name)
            ->assertSee('Tidak bertugas')
            ->assertSee('Tugaskan');
    }

    public function test_setelah_ditugaskan_status_menjadi_sedang_bertugas(): void
    {
        $petugas = $this->petugas();

        $this->toggle($petugas, true)
            ->assertRedirect(route('face.index'))
            ->assertSessionHas('status', $petugas->name.' sekarang boleh memakai kamera absen.');

        $this->assertTrue($petugas->refresh()->can_scan_face);

        $this->page()
            ->assertOk()
            ->assertSee('Sedang bertugas')
            ->assertSee('Cabut tugas');
    }

    public function test_status_penugasan_tetap_benar_setelah_halaman_dibuka_ulang(): void
    {
        $petugas = $this->petugas('Poppy');
        $this->fakeFaceService();

        $this->toggle($petugas, true);

        // Halaman dibuka lagi dengan sesi yang masih ada.
        $this->page()->assertOk()->assertSee('Sedang bertugas');

        // Dan sekali lagi setelah sesi login diganti, seperti admin yang masuk
        // ulang dari peramban lain. Keadaannya harus tetap sama, sebab sumber
        // kebenarannya data pengguna, bukan sesi.
        $this->flushSession();

        $this->page()->assertOk()->assertSee('Sedang bertugas');
    }

    public function test_setelah_tugas_dicabut_status_kembali_tidak_bertugas(): void
    {
        $petugas = $this->petugas('Poppy', assigned: true);
        $this->fakeFaceService();

        $this->assertTrue($petugas->can_scan_face);

        $this->toggle($petugas, false)
            ->assertRedirect(route('face.index'))
            ->assertSessionHas('status', $petugas->name.' tidak lagi boleh memakai kamera absen.');

        $this->assertFalse($petugas->refresh()->can_scan_face);

        $this->page()
            ->assertOk()
            ->assertSee('Tidak bertugas')
            ->assertSee('Tugaskan')
            ->assertDontSee('Sedang bertugas');
    }

    public function test_peran_petugas_tidak_otomatis_dianggap_sedang_bertugas(): void
    {
        $petugas = $this->petugas();
        $this->fakeFaceService();

        $this->assertSame(User::RolePetugas, $petugas->role);
        $this->assertFalse($petugas->isCameraOperator());

        $this->page()->assertOk()->assertSee('Tidak bertugas');

        // Perannya petugas, tetapi penugasannya belum ada, jadi halaman kamera
        // tetap ditolak.
        $this->actingAs($petugas)
            ->get(route('attendance.wajah'))
            ->assertRedirect(route('dashboard'));
    }

    /*
    |--------------------------------------------------------------------------
    | Yang tertulis harus sama dengan yang benar benar boleh masuk
    |--------------------------------------------------------------------------
    */

    public function test_daftar_petugas_memakai_data_yang_sama_dengan_penjaga_hak_akses(): void
    {
        $bertugas = $this->petugas('Poppy', assigned: true);
        $belum = $this->petugas('Nur Hidayah');
        $this->fakeFaceService();

        $this->page()->assertOk()->assertSee('Sedang bertugas')->assertSee('Tidak bertugas');

        // Yang ditugaskan benar benar bisa membuka halaman kamera.
        $this->actingAs($bertugas)->get(route('attendance.wajah'))->assertOk();

        // Yang tidak ditugaskan tidak bisa, walaupun perannya sama.
        $this->actingAs($belum)
            ->get(route('attendance.wajah'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_admin_tetap_bisa_membuka_kamera_walau_tidak_ditugaskan(): void
    {
        $admin = $this->admin();

        $this->assertFalse($admin->isCameraOperator());

        $this->actingAs($admin)->get(route('attendance.wajah'))->assertOk();
    }

    public function test_akun_yang_sudah_bertugas_tetap_terdaftar_supaya_bisa_dicabut(): void
    {
        // Penugasan juga bisa diberikan dari halaman Anggota, termasuk untuk
        // anggota berperan murid. Akun seperti itu harus tetap muncul di sini,
        // sebab kalau tidak, yang bisa membuka kamera absen tidak akan terbaca
        // admin dan tugasnya tidak bisa dicabut.
        $murid = $this->makeUser(User::RoleMurid, ['name' => 'Poppy', 'can_scan_face' => true]);
        $this->fakeFaceService();

        $this->page()->assertOk()->assertSee($murid->name)->assertSee('Sedang bertugas');

        $this->actingAs($murid)->get(route('attendance.wajah'))->assertOk();

        $this->toggle($murid, false)->assertRedirect(route('face.index'));

        $this->assertFalse($murid->refresh()->can_scan_face);

        $this->actingAs($murid)
            ->get(route('attendance.wajah'))
            ->assertRedirect(route('murid.riwayat'));
    }

    public function test_yang_benar_benar_bertugas_ditampilkan_dengan_keadaan_datanya(): void
    {
        $bertugas = $this->petugas('Poppy', assigned: true);
        $belum = $this->petugas('Nur Hidayah');
        $this->fakeFaceService();

        $halaman = $this->page()->assertOk()->getContent();

        // Baris tabel dan kolom penanda tugasnya dibaca dari data yang sama,
        // jadi keadaan yang tertulis di markah pun harus sama.
        $this->assertMatchesRegularExpression(
            '/data-member="'.$bertugas->getKey().'".*?Sedang bertugas/s',
            $halaman,
        );
        $this->assertMatchesRegularExpression(
            '/data-member="'.$belum->getKey().'".*?Tidak bertugas/s',
            $halaman,
        );
    }
}
