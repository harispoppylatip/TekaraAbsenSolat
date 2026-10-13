<?php

namespace App\Support;

use App\Models\User;

/**
 * Susunan menu panel yang menyesuaikan peran pengguna.
 *
 * Menu ditulis satu kali di sini supaya tampilan samping di layar lebar dan
 * batang menu di layar kecil tidak pernah berbeda isi.
 */
class Menu
{
    /**
     * @return array<int, array{label: string, items: array<int, array{label: string, route: string, active: string, roles: array<int, string>}>}>
     */
    public static function groups(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $groups = [
            [
                'label' => 'Utama',
                'items' => [
                    ['label' => 'Ringkasan', 'route' => 'dashboard', 'active' => 'dashboard', 'roles' => [User::RoleAdmin, User::RolePetugas]],
                    ['label' => 'Absen Wajah', 'route' => 'attendance.wajah', 'active' => 'attendance.wajah', 'roles' => [User::RoleAdmin, User::RolePetugas]],
                    ['label' => 'Riwayat Saya', 'route' => 'murid.riwayat', 'active' => 'murid.riwayat', 'roles' => [User::RoleMurid]],
                ],
            ],
            [
                'label' => 'Wajah Saya',
                'items' => [
                    /*
                    | Menu ini hanya muncul pada akun yang wajahnya belum
                    | terdaftar. Ditaruh di kelompok sendiri supaya terbaca
                    | sebagai urusan pribadi pemilik akun, bukan bagian dari
                    | pengaturan sekolah, dan supaya tidak berdesakan dengan
                    | menu yang selalu ada.
                    */
                    ['label' => 'Daftarkan Wajah', 'route' => 'enrollment.show', 'active' => 'enrollment.*', 'roles' => [], 'when' => 'enrollment'],
                ],
            ],
            [
                'label' => 'Data',
                'items' => [
                    ['label' => 'Data Anggota', 'route' => 'members.index', 'active' => 'members.*', 'roles' => [User::RoleAdmin]],
                    ['label' => 'Data Wajah', 'route' => 'face.index', 'active' => 'face.*', 'roles' => [User::RoleAdmin]],
                ],
            ],
            [
                'label' => 'Ibadah',
                'items' => [
                    ['label' => 'Waktu Solat', 'route' => 'prayer-times.index', 'active' => 'prayer-times.*', 'roles' => [User::RoleAdmin]],
                    ['label' => 'Sesi Absen', 'route' => 'sessions.index', 'active' => 'sessions.*', 'roles' => [User::RoleAdmin, User::RolePetugas]],
                ],
            ],
            [
                'label' => 'Laporan',
                'items' => [
                    ['label' => 'Rekap Kehadiran', 'route' => 'reports.index', 'active' => 'reports.*', 'roles' => [User::RoleAdmin, User::RolePetugas]],
                ],
            ],
            [
                'label' => 'Sistem',
                'items' => [
                    ['label' => 'Perangkat', 'route' => 'devices.index', 'active' => 'devices.*', 'roles' => [User::RoleAdmin]],
                ],
            ],
        ];

        $result = [];

        foreach ($groups as $group) {
            $items = array_values(array_filter(
                $group['items'],
                static fn (array $item): bool => self::visible($item, $user),
            ));

            if ($items !== []) {
                /*
                | Kelompok menu kosong tidak ditampilkan sama sekali, jadi
                | kelompok "Wajah Saya" tidak menyisakan judul tanpa isi pada
                | akun yang wajahnya sudah terdaftar.
                */
                $result[] = ['label' => $group['label'], 'items' => $items];
            }
        }

        return $result;
    }

    /**
     * Kelompok menu ringkas untuk batang bawah di layar kecil, ditambah menu
     * pendaftaran wajah bila pemiliknya belum terdaftar.
     *
     * @return array<int, array{label: string, route: string, active: string, roles: array<int, string>}>
     */
    public static function quick(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $items = [
            ['label' => 'Ringkasan', 'route' => 'dashboard', 'active' => 'dashboard', 'roles' => [User::RoleAdmin, User::RolePetugas]],
            ['label' => 'Riwayat', 'route' => 'murid.riwayat', 'active' => 'murid.riwayat', 'roles' => [User::RoleMurid]],
            ['label' => 'Wajah Saya', 'route' => 'enrollment.show', 'active' => 'enrollment.*', 'roles' => [], 'when' => 'enrollment'],
            ['label' => 'Absen', 'route' => 'attendance.wajah', 'active' => 'attendance.wajah', 'roles' => [User::RoleAdmin, User::RolePetugas]],
            ['label' => 'Sesi', 'route' => 'sessions.index', 'active' => 'sessions.*', 'roles' => [User::RoleAdmin, User::RolePetugas]],
            ['label' => 'Rekap', 'route' => 'reports.index', 'active' => 'reports.*', 'roles' => [User::RoleAdmin, User::RolePetugas]],
            ['label' => 'Anggota', 'route' => 'members.index', 'active' => 'members.*', 'roles' => [User::RoleAdmin]],
            ['label' => 'Wajah', 'route' => 'face.index', 'active' => 'face.*', 'roles' => [User::RoleAdmin]],
            ['label' => 'Perangkat', 'route' => 'devices.index', 'active' => 'devices.*', 'roles' => [User::RoleAdmin]],
        ];

        $visible = array_values(array_filter(
            $items,
            static fn (array $item): bool => self::visible($item, $user),
        ));

        /*
        | Batang bawah hanya memuat lima menu paling penting supaya tidak
        | berdesakan di layar sempit. Menu selebihnya selalu tersedia lengkap
        | di menu samping.
        */
        return array_slice($visible, 0, 5);
    }

    /**
     * Apakah satu butir menu pantas ditampilkan untuk pengguna ini.
     *
     * Butir menu boleh membawa syarat tambahan pada kunci `when`, dan syarat
     * itu diperiksa di sini supaya susunan menu hanya ditulis satu kali.
     *
     * @param  array<string, mixed>  $item
     */
    private static function visible(array $item, User $user): bool
    {
        $roles = (array) ($item['roles'] ?? []);

        if ($roles !== [] && ! in_array($user->role, $roles, true)) {
            return false;
        }

        if (($item['when'] ?? null) === 'enrollment') {
            return $user->needsEnrollment();
        }

        return $roles !== [];
    }
}
