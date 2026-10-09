# Menjalankan Absen Solat SMKIM4 dengan Docker

Panduan ini menjelaskan cara menjalankan aplikasi Absen Solat beserta basis
data MySQL-nya di dalam Docker. Seluruh pengaturan dibaca dari berkas `.env`,
jadi `docker-compose.yml` tidak perlu disunting saat alamat server, port, atau
sandi berubah.

Basis data MySQL bawaan compose juga dibuka ke luar wadah, sehingga bisa
diperiksa dan diambil dari komputer lain memakai HeidiSQL, DBeaver, Navicat,
`mysql`, atau alat lain.

## Daftar berkas yang dibuat

| Berkas                      | Isi                                                                             |
| --------------------------- | ------------------------------------------------------------------------------- |
| `Dockerfile`                | Empat tahap: `aset` (Vite), `vendor` (composer), `app` (PHP-FPM), `web` (nginx) |
| `docker-compose.yml`        | Layanan `db`, `app`, `web`, `queue`, `scheduler`                                |
| `docker/entrypoint.sh`      | Menyiapkan folder `storage`, menjalankan migrasi, memilih pengguna perintah     |
| `docker/nginx/default.conf` | Pengaturan nginx: akar `public`, batas unggahan, penerusan ke PHP-FPM           |
| `docker/php/php.ini`        | Batas unggahan, waktu jalan, dan opcache                                        |
| `.dockerignore`             | Berkas yang tidak perlu masuk citra                                             |
| `.env` dan `.env.example`   | Tambahan blok Docker di bagian bawah                                            |

## Yang harus tersedia

Docker Desktop atau Docker Engine dengan Compose. Untuk Windows, cara paling
mudah adalah Docker Desktop dengan backend WSL 2. Sisa ruang cakram yang
disarankan sekitar 3 GB, karena citra MySQL, PHP, dan nginx ikut diunduh.

Pemeriksaan cepat:

```sh
docker --version
docker compose version
```

## Pengaturan di berkas .env

Semua kunci yang khusus Docker memakai akhiran `_DOCKER` supaya `.env` yang
sama tetap bisa dipakai untuk pengembangan di komputer dengan
`php artisan serve`.

| Kunci                     | Arti                                             | Bawaan                             |
| ------------------------- | ------------------------------------------------ | ---------------------------------- |
| `APP_PORT`                | Port web di komputer induk                       | `8080`                             |
| `APP_URL_DOCKER`          | Alamat aplikasi yang dilihat pengguna            | `http://localhost:8080`            |
| `APP_ENV_DOCKER`          | Kesan aplikasi di dalam wadah                    | `production`                       |
| `APP_DEBUG_DOCKER`        | Tampilkan pesan galat terperinci                 | `false`                            |
| `LOG_CHANNEL_DOCKER`      | Catatan aplikasi masuk ke keluaran wadah         | `stderr`                           |
| `LOG_LEVEL_DOCKER`        | Tingkat kerincian catatan                        | `warning`                          |
| `DB_HOST_DOCKER`          | Nama host MySQL yang dilihat dari dalam wadah    | `db`                               |
| `DB_USERNAME_DOCKER`      | Pengguna aplikasi di MySQL                       | `absen_solat`                      |
| `DB_PASSWORD_DOCKER`      | Sandi pengguna aplikasi, wajib diisi             | `AbsenSolat2026`                   |
| `DB_ROOT_PASSWORD`        | Sandi root MySQL bawaan compose, wajib diisi     | `RootAbsenSolat2026`               |
| `DB_PUBLISH_HOST`         | Antarmuka tempat port MySQL dibuka               | `0.0.0.0`                          |
| `DB_PUBLISH_PORT`         | Port MySQL untuk klien luar wadah                | `3307`                             |
| `RUN_MIGRATIONS`          | Jalankan migrasi saat wadah `app` hidup          | `true`                             |
| `FACE_SERVICE_URL_DOCKER` | Alamat mesin wajah yang dilihat dari dalam wadah | `http://host.docker.internal:5005` |

Port web dipilih `8080` supaya tidak bentrok dengan `php artisan serve`
(8000) dan dengan XAMPP atau Laragon (80). Bila aplikasinya dibuka dari
komputer lain, isi `APP_URL_DOCKER` dengan alamat yang dipakai pengguna,
misalnya `http://192.168.1.10:8080`, agar tautan yang dibuat aplikasi ikut
tepat.

Ganti `DB_PASSWORD_DOCKER`, `DB_ROOT_PASSWORD`, dan sandi akun bawaan
sebelum dipakai di jaringan sekolah.

## Langkah memasang

```sh
docker compose up -d --build
```

Pembangunan pertama memakan waktu beberapa menit karena memasang paket npm,
paket composer, dan ekstensi PHP.

Periksa keadaan layanan. Nilai `healthy` berarti layanan benar-benar siap,
bukan sekadar hidup.

```sh
docker compose ps
```

Buka `http://localhost:8080`. Halaman pertama yang muncul adalah halaman
masuk aplikasi.

Bila basis data MySQL-nya masih kosong, isi akun dan waktu solat bawaan
sekali saja. Penyemaian sengaja tidak dijalankan otomatis, supaya sandi akun
yang sudah diganti pengurus tidak pernah tertimpa saat wadah dinyalakan ulang.

```sh
docker compose exec app php artisan db:seed --force
```

Akun bawaan hasil penyemaian:

| Peran   | Surel                                                 | Sandi                         |
| ------- | ----------------------------------------------------- | ----------------------------- |
| Admin   | `admin@smkistiqomah.sch.id`                           | `admin123`                    |
| Petugas | `petugas@smkistiqomah.sch.id`                         | `petugas123`                  |
| Murid   | `ahmad.fauzi@smkistiqomah.sch.id` dan lima murid lain | Nomor induk, misalnya `25001` |

Murid wajib mengganti sandi saat pertama masuk. Perangkat kamera bernama
`kamera-wajah` (tampil sebagai Kamera Absen Masjid) ikut didaftarkan dan
dinyalakan oleh penyemai, jadi halaman Absen Wajah bisa langsung dicoba
setelah ada satu anggota yang wajahnya terdaftar.

## Mengambil basis data MySQL dari luar

MySQL bawaan compose menerima sambungan dari luar wadah pada port
`DB_PUBLISH_PORT` (bawaan 3307), sedangkan di dalam jaringan Docker tetap
port 3306.

| Dari                      | Host                      | Port   | Pengguna      |
| ------------------------- | ------------------------- | ------ | ------------- |
| Dalam wadah aplikasi      | `db`                      | `3306` | `absen_solat` |
| Komputer induk            | `127.0.0.1`               | `3307` | `absen_solat` |
| Komputer lain di jaringan | alamat IP komputer server | `3307` | `absen_solat` |

Contoh dengan `mysql` dari komputer lain:

```sh
mysql -h 192.168.1.10 -P 3307 -u absen_solat -p absen_solat
```

Contoh dengan HeidiSQL, DBeaver, atau Navicat: pilih MySQL, isi alamat
komputer server, port `3307`, pengguna `absen_solat`, sandi sesuai
`DB_PASSWORD_DOCKER`, dan basis data `absen_solat`.

Cadangan dan pemulihan basis data:

```sh
docker compose exec db mysqldump -u root -p absen_solat > cadangan-absen-solat.sql
docker compose exec -T db mysql -u root -p absen_solat < cadangan-absen-solat.sql
```

Bila basis data hanya perlu diakses dari komputer server sendiri, isi
`DB_PUBLISH_HOST=127.0.0.1` di `.env` lalu jalankan
`docker compose up -d`. Dengan begitu port MySQL tidak terbuka ke jaringan.

Catatan keamanan: `MYSQL_ROOT_HOST` bawaan compose adalah `%`, artinya akun
root boleh masuk dari luar wadah. Nilai itu hanya terpakai saat volume basis
data masih kosong. Perketat dengan sandi root yang kuat, atau batasi akses
lewat `DB_PUBLISH_HOST`, atau ubah `MYSQL_ROOT_HOST` menjadi `127.0.0.1`
sebelum menjalankan `docker compose up` pertama kali.

## Memakai MySQL yang sudah ada

Aplikasi tidak harus memakai MySQL bawaan compose. Cukup ubah tiga kunci di
`.env`:

```env
DB_HOST_DOCKER=host.docker.internal
DB_USERNAME_DOCKER=absen_solat
DB_PASSWORD_DOCKER=sandi-di-mysql-anda
```

`host.docker.internal` menunjuk komputer induk, sesuai untuk MySQL Laragon
atau XAMPP yang sudah berjalan di komputer yang sama. Untuk MySQL di komputer
lain, isi alamat IP-nya, misalnya `192.168.1.20`.

Pastikan akun MySQL tersebut boleh masuk dari luar komputer basis data
(`'absen_solat'@'%'`) dan basis datanya sudah dibuat. Jalankan migrasi sendiri
bila perlu:

```sh
docker compose exec app php artisan migrate --force
```

Bila tidak ingin MySQL bawaan compose ikut berjalan, sebutkan layanannya
secara tersendiri:

```sh
docker compose up -d --build app web queue scheduler
```

## Mesin pengenal wajah

Mesin wajah berjalan terpisah dari aplikasi ini (layanan Python). Alamat yang
dipakai dari dalam wadah diatur lewat `FACE_SERVICE_URL_DOCKER` di `.env`.

Dua hal yang sering menjadi penyebab mesin wajah tidak bisa dihubungi:

1. `127.0.0.1` di dalam wadah menunjuk wadah itu sendiri, bukan komputer
   induk. Gunakan `http://host.docker.internal:5005` untuk mesin wajah di
   komputer yang sama, atau alamat IP komputer tersebut.
2. Mesin wajah yang berjalan di komputer induk harus mendengar di semua
   antarmuka, bukan hanya `127.0.0.1`, sebab sambungan dari wadah datang dari
   antarmuka jaringan Docker. Jalankan dengan `FACE_HOST` diisi `0.0.0.0`:

```powershell
$env:FACE_HOST = '0.0.0.0'
.\.venv\Scripts\python.exe face_service.py
```

Salah satu tandanya adalah panel antrean di halaman Wajah berhenti di tengah
jalan dan catatan wadah `queue` memuat pesan mesin wajah tidak bisa
dihubungi.

## Perintah sehari-hari

| Keperluan                                | Perintah                                             |
| ---------------------------------------- | ---------------------------------------------------- |
| Melihat catatan aplikasi                 | `docker compose logs -f app`                         |
| Melihat catatan pekerja antrean          | `docker compose logs -f queue`                       |
| Melihat catatan web                      | `docker compose logs -f web`                         |
| Masuk ke wadah aplikasi                  | `docker compose exec app sh`                         |
| Menjalankan perintah artisan             | `docker compose exec app php artisan migrate:status` |
| Menyalakan ulang setelah mengubah kode   | `docker compose up -d --build`                       |
| Menyalakan ulang setelah mengubah `.env` | `docker compose up -d --force-recreate`              |
| Menghentikan tanpa menghapus data        | `docker compose stop`                                |
| Mematikan dan menghapus wadah            | `docker compose down`                                |
| Menghapus wadah beserta seluruh data     | `docker compose down -v`                             |

Kode aplikasi menyatu di dalam citra. Setelah mengubah berkas PHP, JavaScript,
atau Blade, jalankan `docker compose up -d --build` supaya perubahannya ikut.
Berkas yang sudah dikompilasi bisa dibersihkan dengan
`docker compose exec app php artisan optimize:clear`.

## Kalau ada masalah

| Gejala                                                  | Sebab dan penanganan                                                                                                                                                                                                                             |
| ------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Halaman putih atau galat 500                            | Buka `docker compose logs app`. Isi `APP_DEBUG_DOCKER=true` di `.env` lalu jalankan ulang untuk melihat pesan terperinci, dan kembalikan ke `false` setelah selesai                                                                              |
| Galat 419 Page Expired                                  | Sesi gagal disimpan. Pastikan migrasi sudah jalan (`docker compose exec app php artisan migrate:status`) sehingga tabel `sessions` ada, dan `APP_KEY` di `.env` tidak berubah dari sebelumnya. Semua sesi lama juga batal bila `APP_KEY` diganti |
| Masuk berhasil tetapi langsung kembali ke halaman masuk | Sesi tidak tersimpan. Periksa catatan wadah `app` dan pastikan waktu di komputer server benar                                                                                                                                                    |
| Galat 502 Bad Gateway                                   | Wadah `app` belum siap atau sedang dimulai. Lihat `docker compose ps` dan `docker compose logs app`                                                                                                                                              |
| Galat 504 atau halaman berhenti saat mengunggah         | Batas waktu nginx dan batas ukuran unggahan. Nilai bawaannya sudah berjenjang: nginx 96 MB, `post_max_size` 64 MB, satu foto 5 MB                                                                                                                |
| Kartu kemajuan unggahan wajah berhenti                  | Wadah `queue` mati atau mesin wajah tidak bisa dihubungi. Periksa `docker compose ps` dan `docker compose logs queue`                                                                                                                            |
| Access denied for user                                  | Sandi di `DB_PASSWORD_DOCKER` tidak sama dengan sandi yang sudah tersimpan di volume MySQL. Kembalikan sandi lama, atau hapus volume dengan `docker compose down -v` bila datanya memang boleh hilang                                            |
| Port sudah dipakai                                      | Ubah `APP_PORT` atau `DB_PUBLISH_PORT` di `.env`, lalu `docker compose up -d`                                                                                                                                                                    |
| Hubungan ke MySQL lambat atau gagal berulang            | Pastikan volume basis data `absen-solat-dbdata` masih ada (`docker volume ls`) dan `docker compose logs db` tidak memuat galat                                                                                                                   |
| Pembangunan berhenti pada pemeriksaan versi PHP         | Citra PHP yang dipakai lebih tua dari 8.4.1. Jangan turunkan citra; naikkan versinya                                                                                                                                                             |

Perubahan besar pada `docker-compose.yml` atau `Dockerfile` bisa diperiksa
tanpa menjalankan apa pun:

```sh
docker compose config --quiet
```

## Catatan teknis yang perlu diketahui

1. Citra PHP memakai 8.5. `composer.json` menulis `^8.3`, tetapi yang
   menentukan sesungguhnya adalah `composer.lock`: berkas
   `vendor/composer/platform_check.php` menuntut PHP 8.4.1 atau lebih baru.
   Pada proyek lain, memakai citra PHP yang lebih tua membuat wadah `queue`
   dan `scheduler` mati berulang dengan kode 255, sementara wadah `app` tetap
   tertulis sehat sebab pemeriksaan kesehatannya hanya membuka soket FastCGI.
   Karena itu di sini ada dua penjaga: pemeriksaan versi saat pembangunan, dan
   pemeriksaan kesehatan yang benar-benar menjalankan Laravel.
2. `opcache`, `pdo_sqlite`, dan `sqlite3` tidak diminta lewat
   `docker-php-ext-install` sebab ketiganya sudah menyatu di citra resmi PHP.
   Memintanya membuat pembangunan gagal dengan kode keluar 2. Yang dipasang
   hanya `pdo_mysql`, `pcntl`, dan bila perlu `mbstring` serta `curl`.
3. nginx hanya mendengar IPv4. Pendengar IPv6 pernah membuat nginx gagal
   hidup dengan galat alamat keluarga tidak didukung pada mesin tanpa IPv6.
4. Nama layanan `app` dicari lewat resolver Docker pada setiap permintaan,
   bukan diingat sekali. Tanpa itu, setelah wadah `app` dibangun ulang nginx
   menembak alamat lama dan semua halaman menjadi 502.
5. Folder `storage` disimpan sebagai volume karena foto wajah yang menunggu
   diubah berada di `storage/app/private`. Tanpa volume, wadah web dan wadah
   pekerja antrean memakai folder berbeda dan unggahan hilang tanpa jejak.
6. Migrasi berjalan sendiri saat wadah `app` hidup, tetapi penyemaian tidak.
   Penyemai menimpa waktu solat bawaan, jadi jalankan hanya pada basis data
   yang masih kosong.
7. Catatan aplikasi dikirim ke keluaran wadah (`LOG_CHANNEL=stderr`) supaya
   terbaca dengan `docker compose logs` dan tidak menumpuk di dalam volume.

## Berkas terkait

- `README.md`, pengenalan aplikasi dan cara menjalankan tanpa Docker.
- `config/face.php` beserta blok `FACE_*` di `.env`, pengaturan mesin wajah.
- Mesin pengenal wajah, layanan Python terpisah yang tidak ikut dijalankan
  oleh compose ini dan cukup dihubungi lewat alamat di `.env`.
