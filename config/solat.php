<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Toleransi keterlambatan (menit)
    |--------------------------------------------------------------------------
    |
    | Anggota yang absen lebih dari jumlah menit ini setelah waktu mulai
    | solat dicatat sebagai terlambat, selebihnya hadir.
    |
    */

    'late_minutes' => (int) env('SOLAT_LATE_MINUTES', 10),

    /*
    |--------------------------------------------------------------------------
    | Perangkat kamera absen bawaan
    |--------------------------------------------------------------------------
    */

    'device_id' => env('SOLAT_DEVICE_ID', 'kamera-wajah'),

    /*
    |--------------------------------------------------------------------------
    | Waktu solat bawaan
    |--------------------------------------------------------------------------
    |
    | Dipakai saat menyemai data dan saat membuat sesi otomatis.
    | Format jam memakai 24 jam tanpa detik.
    |
    */

    'prayer_defaults' => [
        ['prayer_name' => 'subuh', 'label' => 'Subuh', 'start_time' => '04:45', 'end_time' => '05:30', 'sort_order' => 1],
        ['prayer_name' => 'zuhur', 'label' => 'Zuhur', 'start_time' => '12:00', 'end_time' => '12:45', 'sort_order' => 2],
        ['prayer_name' => 'ashar', 'label' => 'Ashar', 'start_time' => '15:15', 'end_time' => '16:00', 'sort_order' => 3],
        ['prayer_name' => 'maghrib', 'label' => 'Maghrib', 'start_time' => '18:05', 'end_time' => '18:50', 'sort_order' => 4],
        ['prayer_name' => 'isya', 'label' => 'Isya', 'start_time' => '19:05', 'end_time' => '19:50', 'sort_order' => 5],
    ],

    /*
    |--------------------------------------------------------------------------
    | Daftar peran
    |--------------------------------------------------------------------------
    */

    'roles' => [
        'admin' => 'Administrator',
        'petugas' => 'Petugas',
        'murid' => 'Murid / Jamaah',
    ],

];
