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
    | Sesi absen otomatis
    |--------------------------------------------------------------------------
    |
    | Sesi absen disiapkan, dibuka, dan ditutup sendiri oleh penjadwal
    | aplikasi. Angka di bawah ini dipakai mesin sesi maupun batas buka
    | petugas, jadi keduanya selalu memakai angka yang sama.
    |
    | early_open_minutes  menit sebelum jam mulai saat sesi sudah boleh dibuka
    | create_time         jam penjadwal menyiapkan sesi hari itu (24 jam)
    | prepare_days_ahead  berapa hari sesudah hari ini ikut disiapkan
    |
    */

    'early_open_minutes' => (int) env('SOLAT_EARLY_OPEN_MINUTES', 10),

    'create_time' => env('SOLAT_SESSION_CREATE_TIME', '00:05'),

    'prepare_days_ahead' => (int) env('SOLAT_PREPARE_DAYS_AHEAD', 1),

    /*
    |--------------------------------------------------------------------------
    | Sesi uji coba absen
    |--------------------------------------------------------------------------
    |
    | Admin bisa menyalakan mode uji coba supaya kamera absen wajah bisa dicoba
    | tanpa membuka sesi waktu solat yang sungguhan. Catatan percobaan tidak
    | dihitung pada rekap mana pun, jadi waktunya bebas.
    |
    | test_cooldown_minutes  jeda sebelum orang yang sama boleh tercatat lagi
    |                        pada mode uji coba; isi 0 supaya jedanya dimatikan
    |
    */

    'test_cooldown_minutes' => (int) env('SOLAT_TEST_COOLDOWN_MINUTES', 4),

    /*
    |--------------------------------------------------------------------------
    | Bukti foto absen
    |--------------------------------------------------------------------------
    |
    | Setiap absen wajah yang berhasil menyimpan foto orangnya sebagai bukti.
    | Fotonya sudah diperkecil mesin wajah sebelum dikirim, jadi yang tersimpan
    | hanya berkas kecil. Foto bukti tidak pernah menggagalkan absen: bila
    | penyimpanannya bermasalah, absennya tetap tercatat tanpa foto.
    |
    | keep_proof_photo  false berarti tidak ada foto yang diminta atau disimpan
    | proof_max_bytes   batas aman ukuran foto yang mau diterima dari mesin
    |                   wajah; balasan yang melebihi batas ini diabaikan
    | proof_retention_days  umur simpan bukti sebelum dibersihkan penjadwal;
    |                   isi 0 supaya bukti disimpan selamanya
    |
    */

    'keep_proof_photo' => (bool) env('SOLAT_KEEP_PROOF_PHOTO', true),

    'proof_max_bytes' => (int) env('SOLAT_PROOF_MAX_BYTES', 262144),

    'proof_retention_days' => (int) env('SOLAT_PROOF_RETENTION_DAYS', 90),

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
