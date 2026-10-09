<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Alamat layanan pengenal wajah
    |--------------------------------------------------------------------------
    |
    | Mesin wajah berjalan sebagai layanan Python terpisah. Ubah nilai
    | FACE_SERVICE_URL di berkas .env bila mesin berada di komputer lain.
    |
    */

    'service_url' => env('FACE_SERVICE_URL', 'http://127.0.0.1:5005'),

    /*
    |--------------------------------------------------------------------------
    | Toleransi jarak
    |--------------------------------------------------------------------------
    |
    | Semakin kecil angkanya, semakin ketat pencocokan. Nilai 0,6 adalah
    | bawaan yang lazim dipakai untuk model dlib.
    |
    */

    'tolerance' => (float) env('FACE_TOLERANCE', 0.6),

    /*
    |--------------------------------------------------------------------------
    | Model yang dipakai
    |--------------------------------------------------------------------------
    */

    'model' => env('FACE_MODEL', 'dlib-resnet-v1'),

    /*
    |--------------------------------------------------------------------------
    | Batas waktu tunggu (detik)
    |--------------------------------------------------------------------------
    |
    | Satu foto bisa memakan 12 sampai 15 detik pada mesin tanpa GPU.
    |
    */

    'timeout' => (int) env('FACE_SERVICE_TIMEOUT', 90),

    /*
    |--------------------------------------------------------------------------
    | Perkiraan waktu satu foto (detik)
    |--------------------------------------------------------------------------
    |
    | Dipakai hanya untuk menghitung kemajuan di panel Proses pendaftaran wajah.
    | Mesin tanpa GPU memerlukan sekitar 15 detik untuk satu foto; mesin dengan
    | GPU jauh lebih cepat, jadi angkanya bisa diturunkan lewat
    | FACE_EXPECTED_SECONDS di berkas .env supaya persennya tidak terlalu cepat
    | menyentuh batas atas.
    |
    */

    'expected_seconds' => (int) env('FACE_EXPECTED_SECONDS', 15),

    /*
    |--------------------------------------------------------------------------
    | Perangkat kamera bawaan
    |--------------------------------------------------------------------------
    */

    'device_id' => env('FACE_DEVICE_ID', 'kamera-wajah'),

    /*
    |--------------------------------------------------------------------------
    | Batas jumlah foto wajah per anggota
    |--------------------------------------------------------------------------
    */

    'max_photos_per_user' => 20,

    /*
    |--------------------------------------------------------------------------
    | Batas jumlah foto per sekali kirim
    |--------------------------------------------------------------------------
    |
    | Pengubahan foto menjadi angka wajah dikerjakan di antrean, bukan di dalam
    | permintaan web, jadi kiriman besar tidak lagi membuat halaman kehabisan
    | waktu. Batas sepuluh hanya menjaga agar antrean tetap masuk akal: sepuluh
    | foto berarti sekitar dua setengah menit menunggu di antrean.
    |
    */

    'max_photos_per_submit' => 10,

];
