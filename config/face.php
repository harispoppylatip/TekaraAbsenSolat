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
    | Angka ini adalah JARAK, bukan kemiripan: semakin kecil, semakin mirip.
    | Layanan wajah InsightFace menormalkan angkanya lebih dulu, sehingga jarak
    | euclid di sini setara dengan cosine similarity.
    |
    | Nilai 1,15 dipilih dari pengukuran tiga himpunan foto sungguhan di mesin
    | pengembang (5 orang, 34 foto). Pada ketiga pengukuran itu, celah aman
    | berada di antara 1,1427 dan 1,1650, jadi 1,15 berada di dalam celah
    | ketiganya sekaligus:
    |
    |   wajah orang yang sama    : jarak 0,4519 sampai 1,1427
    |   wajah orang yang berbeda : jarak 1,1650 sampai 1,5032
    |   pada ambang 1,11 (17 foto): orang lain lolos 0 dari 112,
    |                              orang sama ditolak 0 dari 24
    |
    | Sebagai pembanding, model dlib sebelum ini memakai jarak dengan ambang
    | 0,5. Angka lama itu TIDAK berlaku di sini karena skalanya berbeda.
    |
    | Lima orang masih sampel kecil. Wajib diukur ulang dengan foto sungguhan
    | di sekolah sebelum dipakai untuk kehadiran yang dihitung: kumpulkan 10
    | sampai 20 foto per orang, lalu jalankan `python verifikasi.py --jarak 1.15`
    | di folder layanan wajah. Mengubah angkanya membuat keputusan lama tidak
    | bisa dibandingkan lagi dengan yang baru.
    |
    | Setiap kunci di bawah ini juga bisa diatur lewat berkas .env.
    |
    */

    'tolerance' => (float) env('FACE_TOLERANCE', 1.15),

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

    'single_photo_tolerance' => (float) env('FACE_SINGLE_PHOTO_TOLERANCE', 1.04),

    /*
    |--------------------------------------------------------------------------
    | Selisih minimal juara pertama dan kedua
    |--------------------------------------------------------------------------
    |
    | Bila dua anggota sama-sama dekat, hasilnya tidak boleh dipilih diam-diam.
    | Pencocokan seperti itu dilaporkan sebagai ambigu supaya dipindai ulang.
    |
    */

    'min_margin' => (float) env('FACE_MIN_MARGIN', 0.14),

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
    |
    | Nilai ini WAJIB sama dengan FACE_MODEL_NAME di layanan wajah Python.
    | Nama yang dilaporkan mesin InsightFace adalah "insightface-" diikuti nama
    | modelnya, contoh insightface-buffalo_sc untuk model ringan dan
    | insightface-buffalo_l untuk model berat.
    |
    | Angka sidik wajah juga wajib sama panjangnya. Panjangnya diatur oleh
    | FaceEmbedding::Dimensions dan tidak bisa diatur lewat berkas ini, supaya
    | hanya ada satu sumber kebenaran.
    |
    */

    'model' => env('FACE_MODEL', 'insightface-buffalo_sc'),

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
    | Model InsightFace buffalo_sc jauh lebih cepat daripada model dlib
    | sebelumnya: satu foto sampai terkirim balik terukur 44 milidetik di mesin
    | tanpa kartu grafis, sedangkan dlib memerlukan 12 sampai 15 detik. Batas 30
    | detik tetap disediakan supaya server sekolah yang jauh lebih lemah atau
    | pemuatan model pertama tidak langsung gagal.
    |
    */

    'timeout' => (int) env('FACE_SERVICE_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Perkiraan waktu satu foto (detik)
    |--------------------------------------------------------------------------
    |
    | Dipakai hanya untuk menghitung kemajuan di panel Proses pendaftaran wajah.
    | Model InsightFace buffalo_sc memerlukan sekitar 44 milidetik per foto di
    | mesin pengembang, jadi dua detik sudah memberi ruang yang longgar untuk
    | server yang lebih lemah. Bila kemajuannya terlihat terlalu cepat atau
    | terlalu lambat, sesuaikan lewat FACE_EXPECTED_SECONDS di berkas .env.
    |
    */

    'expected_seconds' => (int) env('FACE_EXPECTED_SECONDS', 2),

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
