<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\PrayerTime;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Ketentuan akun yang disemai.
     *
     * Pengurus dan murid yang belum pernah masuk tetap memakai sandi bawaan
     * supaya wajib ganti sandi benar-benar terjadi saat pertama masuk.
     *
     * @var array<int, array{name: string, identifier_number: string, email: string, role: string, class_name: ?string, phone_number: ?string, can_scan_face: bool, ready: bool}>
     */
    private const Accounts = [
        [
            'name' => 'Admin Absen Solat',
            'identifier_number' => 'ADM-001',
            'email' => 'admin@smkistiqomah.sch.id',
            'role' => User::RoleAdmin,
            'class_name' => null,
            'phone_number' => '0812 5100 0001',
            'can_scan_face' => true,
            'ready' => true,
        ],
        [
            'name' => 'murid',
            'identifier_number' => '25006',
            'email' => 'murid@smkistiqomah.sch.id',
            'role' => User::RoleMurid,
            'class_name' => 'XII TKRO 1',
            'phone_number' => null,
            'can_scan_face' => false,
            'ready' => false,
        ],
    ];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->seedAccounts();
        $this->seedPrayerTimes();
        $this->seedCamera();
    }

    /**
     * Akun pengurus, petugas kamera, dan murid.
     */
    private function seedAccounts(): void
    {
        foreach (self::Accounts as $account) {
            $user = User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'identifier_number' => $account['identifier_number'],
                    'role' => $account['role'],
                    'class_name' => $account['class_name'],
                    'phone_number' => $account['phone_number'],
                    'can_scan_face' => $account['can_scan_face'],
                    'password' => $this->passwordFor($account),
                ],
            );

            /*
            | Akun yang sudah siap dipakai ditandai sudah pernah ganti sandi,
            | supaya pengurus bisa langsung masuk. Sisanya dibiarkan kosong
            | supaya wajib ganti sandi tetap berlaku.
            */
            if ($user->wasRecentlyCreated && $account['ready']) {
                $user->forceFill(['password_changed_at' => Carbon::now()])->save();
            }
        }
    }

    /**
     * Pengurus memakai sandi tetap yang mudah diingat, murid memakai nomor
     * induk seperti ketentuan sandi bawaan.
     *
     * @param  array{role: string, identifier_number: string, ready: bool}  $account
     */
    private function passwordFor(array $account): string
    {
        if (! $account['ready']) {
            return User::defaultPasswordFor($account['role'], $account['identifier_number']);
        }

        return $account['role'] === User::RoleAdmin ? 'admin123' : 'petugas123';
    }

    /**
     * Waktu solat awal sekolah, masih bisa diubah pengurus di halaman Waktu Solat.
     */
    private function seedPrayerTimes(): void
    {
        foreach (config('solat.prayer_defaults') as $prayer) {
            PrayerTime::updateOrCreate(
                ['prayer_name' => $prayer['prayer_name']],
                [
                    'label' => $prayer['label'],
                    'start_time' => $prayer['start_time'],
                    'end_time' => $prayer['end_time'],
                    'late_minutes' => config('solat.late_minutes'),
                    'sort_order' => $prayer['sort_order'],
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * Kamera absen wajah bawaan. Perangkat ini sudah didaftarkan dan dinyalakan
     * supaya halaman Absen Wajah bisa langsung dicoba.
     */
    private function seedCamera(): void
    {
        Device::updateOrCreate(
            ['device_id' => config('solat.device_id')],
            [
                'name' => 'Kamera Absen Masjid',
                'type' => Device::TypeGerbang,
                'status' => Device::StatusAktif,
                'location' => 'Depan masjid sekolah',
                'last_seen_at' => Carbon::now(),
            ],
        );
    }
}
