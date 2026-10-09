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
                static fn (array $item): bool => in_array($user->role, $item['roles'], true),
            ));

            if ($items !== []) {
                $result[] = ['label' => $group['label'], 'items' => $items];
            }
        }

        return $result;
    }

    /**
     * Menu ringkas untuk batang bawah di layar kecil.
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
            ['label' => 'Absen', 'route' => 'attendance.wajah', 'active' => 'attendance.wajah', 'roles' => [User::RoleAdmin, User::RolePetugas]],
            ['label' => 'Sesi', 'route' => 'sessions.index', 'active' => 'sessions.*', 'roles' => [User::RoleAdmin, User::RolePetugas]],
            ['label' => 'Rekap', 'route' => 'reports.index', 'active' => 'reports.*', 'roles' => [User::RoleAdmin, User::RolePetugas]],
            ['label' => 'Anggota', 'route' => 'members.index', 'active' => 'members.*', 'roles' => [User::RoleAdmin]],
            ['label' => 'Wajah', 'route' => 'face.index', 'active' => 'face.*', 'roles' => [User::RoleAdmin]],
            ['label' => 'Perangkat', 'route' => 'devices.index', 'active' => 'devices.*', 'roles' => [User::RoleAdmin]],
        ];

        $visible = array_values(array_filter(
            $items,
            static fn (array $item): bool => in_array($user->role, $item['roles'], true),
        ));

        /*
        | Batang bawah hanya memuat lima menu paling penting supaya tidak
        | berdesakan di layar sempit. Menu selebihnya selalu tersedia lengkap
        | di menu samping.
        */
        return array_slice($visible, 0, 5);
    }
}
