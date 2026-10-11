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
    | Ambang penerimaan identitas
    |--------------------------------------------------------------------------
    |
    | Semakin kecil angkanya, semakin ketat pencocokan. Nilai bawaan 0,5 dipilih
    | dari pengukuran di mesin sekolah: wajah orang yang berbeda berada pada
    | jarak 0,53 sampai 0,71, sedangkan wajah orang yang sama pada potret yang
    | layak berada di bawah 0,5. Angka lama 0,6 terbukti meloloskan orang lain
    | menjadi anggota yang sudah terdaftar.
    |
    | Nilai ini wajib diukur ulang dengan foto sungguhan di sekolah sebelum
    | dipakai untuk kehadiran yang dihitung. Mengubah angkanya membuat
    | keputusan lama tidak bisa dibandingkan lagi dengan yang baru.
    |
    | Setiap kunci di bawah ini juga bisa diatur lewat berkas .env.
    |
    */

    'tolerance' => (float) env('FACE_TOLERANCE', 0.5),

    /*
    |--------------------------------------------------------------------------
    | Berapa foto yang dihitung saat menilai satu anggota
    |--------------------------------------------------------------------------
    |
    | Jarak seorang anggota bukan jarak ke satu foto terdekatnya, melainkan
    | rata-rata beberapa jarak terdekatnya. Alasannya: jarak terdekat membuat
    | satu foto anggota yang buruk menjadi kunci bagi semua orang, sedangkan
    | memakai seluruh foto menghukum orang yang sebenarnya cocok hanya karena
    | sudut atau cahaya fotonya berbeda. Rata-rata dua foto terdekat masih
    | menghargai perbedaan sudut, tetapi menuntut dua foto yang mendukung.
    |
    */

    'aggregate_photos' => (int) env('FACE_AGGREGATE_PHOTOS', 2),

    /*
    |--------------------------------------------------------------------------
    | Aturan anggota berfoto tunggal
    |--------------------------------------------------------------------------
    |
    | Anggota yang jumlah fotonya kurang dari angka di atas tidak punya foto
    | pendukung, jadi jaraknya wajib lebih dekat daripada ambang biasa.
    | Pendaftaran tiga foto atau lebih per anggota sangat dianjurkan.
    |
    */

    'single_photo_tolerance' => (float) env('FACE_SINGLE_PHOTO_TOLERANCE', 0.45),

    /*
    |--------------------------------------------------------------------------
    | Selisih minimal juara pertama dan kedua
    |--------------------------------------------------------------------------
    |
    | Bila dua anggota sama-sama dekat, hasilnya tidak boleh dipilih diam-diam.
    | Pencocokan seperti itu dilaporkan sebagai ambigu supaya dipindai ulang.
    |
    */

    'min_margin' => (float) env('FACE_MIN_MARGIN', 0.06),

    /*
    |--------------------------------------------------------------------------
    | Sisi wajah paling kecil yang boleh diputuskan
    |--------------------------------------------------------------------------
    |
    | Wajah yang terlalu kecil di bingkai foto menghasilkan angka yang tidak
    | bisa dipercaya, tetapi tetap terlihat seperti angka yang wajar. Potret
    | seperti itu ditolak lebih dulu dengan permintaan mendekat ke kamera.
    | Angka 0 mematikan pemeriksaan ini.
    |
    */

    'min_face_size' => (int) env('FACE_MIN_FACE_SIZE', 80),

    /*
    |--------------------------------------------------------------------------
    | Model yang dipakai
    |--------------------------------------------------------------------------
    */

    'model' => env('FACE_MODEL', 'dlib-resnet-v1'),

    /*
    |--------------------------------------------------------------------------
    | Tolak angka wajah dari model lain
    |--------------------------------------------------------------------------
    |
    | Angka wajah hanya bisa dibandingkan bila dihitung oleh model yang sama.
    | Bila pemeriksaan ini hidup, baris angka wajah yang modelnya berbeda
    | dilewati supaya tidak ikut dihitung.
    |
    */

    'enforce_model' => (bool) env('FACE_ENFORCE_MODEL', true),

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
