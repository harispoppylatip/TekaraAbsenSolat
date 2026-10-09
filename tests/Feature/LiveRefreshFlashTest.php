<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman yang menyegarkan wilayahnya sendiri memanggil ulang alamat halaman
 * yang sama setiap lima detik. Permintaan itu ikut memakan pesan sekali-tampil,
 * sehingga pesan "berhasil disimpan" hilang sebelum sempat dibaca. Permintaan
 * penyegaran ditandai header X-Live-Refresh dan pesannya dikembalikan oleh
 * middleware KeepFlashDataOnLiveRefresh.
 */
class LiveRefreshFlashTest extends TestCase
{
    use RefreshDatabase;

    private function registerDevice(): string
    {
        $admin = $this->makeUser(User::RoleAdmin);

        $device = Device::create([
            'device_id' => 'kamera-gerbang-1',
            'name' => 'Kamera Gerbang',
            'type' => Device::TypeGerbang,
            'status' => Device::StatusBelumDidaftarkan,
        ]);

        $this->actingAs($admin)->put(route('devices.register', $device))->assertRedirect();

        return 'Perangkat '.$device->name.' berhasil didaftarkan. Nyalakan setelah penempatannya benar.';
    }

    public function test_penyegaran_otomatis_mempertahankan_pesan_sekali_tampil(): void
    {
        $message = $this->registerDevice();
        $admin = User::query()->where('role', User::RoleAdmin)->firstOrFail();

        // Penyegaran otomatis menampilkan pesannya.
        $this->actingAs($admin)
            ->withHeaders(['X-Live-Refresh' => '1'])
            ->get(route('devices.index'))
            ->assertOk()
            ->assertSee($message);

        // Pesan itu belum habis, jadi masih terbaca pada permintaan berikutnya.
        $this->actingAs($admin)
            ->get(route('devices.index'))
            ->assertOk()
            ->assertSee($message);
    }

    public function test_tanpa_penyegaran_pesan_sekali_tampil_habis_dibaca(): void
    {
        $message = $this->registerDevice();
        $admin = User::query()->where('role', User::RoleAdmin)->firstOrFail();

        $this->actingAs($admin)
            ->get(route('devices.index'))
            ->assertOk()
            ->assertSee($message);

        $this->actingAs($admin)
            ->get(route('devices.index'))
            ->assertOk()
            ->assertDontSee($message);
    }

    public function test_penyegaran_otomatis_tanpa_pesan_tetap_berjalan(): void
    {
        $admin = $this->makeUser(User::RoleAdmin);

        $this->actingAs($admin)
            ->withHeaders(['X-Live-Refresh' => '1'])
            ->get(route('devices.index'))
            ->assertOk()
            ->assertSee('Perangkat');
    }
}
