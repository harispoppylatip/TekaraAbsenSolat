# Menjalankan Absen Solat SMKIM4 dengan Docker

Panduan ini menjelaskan cara menjalankan aplikasi Absen Solat beserta basis
data MySQL-nya di dalam Docker. Seluruh pengaturan dibaca dari file `.env`,
jadi `docker-compose.yml` tidak perlu diubah saat alamat server, port, atau
password berubah.

Database MySQL bawaan compose juga dibuka ke luar container, sehingga bisa
diperiksa dan diambil dari komputer lain memakai HeidiSQL, DBeaver, Navicat,
`mysql`, atau aplikasi lain.

## Daftar file yang dibuat

| File                        | Isi                                                                             |
| --------------------------- | ------------------------------------------------------------------------------- |
| `Dockerfile`                | Empat tahap: `aset` (Vite), `vendor` (composer), `app` (PHP-FPM), `web` (nginx) |
| `docker-compose.yml`        | Layanan `db`, `app`, `web`, `queue`, `scheduler`                                |
| `docker/entrypoint.sh`      | Menyiapkan folder `storage`, menjalankan migrasi, memilih user perintah         |
| `docker/nginx/default.conf` | Pengaturan nginx: akar `public`, batas upload, forward ke PHP-FPM               |
| `docker/php/php.ini`        | Batas upload, waktu jalan, dan opcache                                          |
| `.dockerignore`             | File yang tidak perlu masuk image                                               |
| `.env` dan `.env.example`   | Tambahan blok Docker di bagian bawah                                            |

## Yang harus tersedia

Docker Desktop atau Docker Engine dengan Compose. Untuk Windows, cara paling
mudah adalah Docker Desktop dengan backend WSL 2. Sisa ruang disk yang
disarankan sekitar 3 GB, karena image MySQL, PHP, dan nginx ikut diunduh.

Pemeriksaan cepat:

```sh
docker --version
docker compose version
```

## Pengaturan di file .env

Semua kunci yang khusus Docker memakai akhiran `_DOCKER` supaya `.env` yang
sama tetap bisa dipakai untuk pengembangan di komputer dengan
`php artisan serve`.

| Kunci                     | Arti                                                 | Bawaan                             |
| ------------------------- | ---------------------------------------------------- | ---------------------------------- |
| `APP_PORT`                | Port web di komputer host                            | `8080`                             |
| `APP_URL_DOCKER`          | Alamat aplikasi yang dilihat pengguna                | `http://localhost:8080`            |
| `APP_ENV_DOCKER`          | Lingkungan aplikasi di dalam container               | `production`                       |
| `APP_DEBUG_DOCKER`        | Tampilkan pesan error terperinci                     | `false`                            |
| `LOG_CHANNEL_DOCKER`      | Log aplikasi masuk ke output container               | `stderr`                           |
| `LOG_LEVEL_DOCKER`        | Tingkat detail log                                   | `warning`                          |
| `DB_HOST_DOCKER`          | Nama host MySQL yang dilihat dari dalam container    | `db`                               |
| `DB_USERNAME_DOCKER`      | User aplikasi di MySQL                               | `absen_solat`                      |
| `DB_PASSWORD_DOCKER`      | Password user aplikasi, wajib diisi                  | `AbsenSolat2026`                   |
| `DB_ROOT_PASSWORD`        | Password root MySQL bawaan compose, wajib diisi      | `RootAbsenSolat2026`               |
| `DB_PUBLISH_HOST`         | Interface jaringan tempat port MySQL dibuka          | `0.0.0.0`                          |
| `DB_PUBLISH_PORT`         | Port MySQL untuk klien luar container                | `3307`                             |
| `RUN_MIGRATIONS`          | Jalankan migrasi saat container `app` hidup          | `true`                             |
| `FACE_SERVICE_URL_DOCKER` | Alamat mesin wajah yang dilihat dari dalam container | `http://host.docker.internal:5005` |

Port web dipilih `8080` supaya tidak bentrok dengan `php artisan serve`
(8000) dan dengan XAMPP atau Laragon (80). Bila aplikasinya dibuka dari
komputer lain, isi `APP_URL_DOCKER` dengan alamat yang dipakai pengguna,
misalnya `http://192.168.1.10:8080`, agar tautan yang dibuat aplikasi ikut
tepat.

### Mengganti port

Port hanya diatur di `.env`, jadi image tidak perlu dibangun ulang
(`--build` tidak diperlukan):

- `APP_PORT` mengganti port web di komputer host, misalnya menjadi
  `9000`. Bila kunci ini diubah, `APP_URL_DOCKER` wajib disamakan, misalnya
  `http://localhost:9000`.
- `DB_PUBLISH_PORT` mengganti port MySQL yang dibuka ke klien luar,
  misalnya menjadi `3310`. Port di dalam jaringan Docker tetap `3306`, jadi
  aplikasi tidak terpengaruh.

Setelah `.env` diubah, jalankan `docker compose up -d` supaya container
dibuat ulang dengan pemetaan port yang baru. Port lama langsung tidak
dipakai lagi. Bila port dari komputer lain tidak bisa dibuka, periksa
aturan firewall Windows untuk port tersebut.

Ganti `DB_PASSWORD_DOCKER`, `DB_ROOT_PASSWORD`, dan password akun bawaan
sebelum dipakai di jaringan sekolah.

## Langkah memasang

```sh
docker compose up -d --build
```

Build pertama memakan waktu beberapa menit karena memasang paket npm,
paket composer, dan ekstensi PHP.

Periksa keadaan layanan. Nilai `healthy` berarti layanan benar-benar siap,
bukan sekadar hidup.

```sh
docker compose ps
```

Buka `http://localhost:8080`. Halaman pertama yang muncul adalah halaman
masuk aplikasi.

Bila database MySQL-nya masih kosong, isi akun dan waktu solat bawaan
sekali saja. Seeding sengaja tidak dijalankan otomatis, supaya password akun
yang sudah diganti pengurus tidak pernah tertimpa saat container dinyalakan ulang.

```sh
docker compose exec app php artisan db:seed --force
```

Akun bawaan hasil seeding:

| Peran   | Email                                                 | Password                      |
| ------- | ----------------------------------------------------- | ----------------------------- |
| Admin   | `admin@smkistiqomah.sch.id`                           | `admin123`                    |
| Petugas | `petugas@smkistiqomah.sch.id`                         | `petugas123`                  |
| Murid   | `ahmad.fauzi@smkistiqomah.sch.id` dan lima murid lain | Nomor induk, misalnya `25001` |

Murid wajib mengganti password saat pertama masuk. Perangkat kamera bernama
`kamera-wajah` (tampil sebagai Kamera Absen Masjid) ikut didaftarkan dan
dinyalakan oleh seeder, jadi halaman Absen Wajah bisa langsung dicoba
setelah ada satu anggota yang wajahnya terdaftar.

## Mengambil database MySQL dari luar

MySQL bawaan compose menerima sambungan dari luar container pada port
`DB_PUBLISH_PORT` (bawaan 3307), sedangkan di dalam jaringan Docker tetap
port 3306.

| Dari                      | Host                      | Port   | User          |
| ------------------------- | ------------------------- | ------ | ------------- |
| Dalam container aplikasi  | `db`                      | `3306` | `absen_solat` |
| Komputer host             | `127.0.0.1`               | `3307` | `absen_solat` |
| Komputer lain di jaringan | alamat IP komputer server | `3307` | `absen_solat` |

Contoh dengan `mysql` dari komputer lain:

```sh
mysql -h 192.168.1.10 -P 3307 -u absen_solat -p absen_solat
```

Contoh dengan HeidiSQL, DBeaver, atau Navicat: pilih MySQL, isi alamat
komputer server, port `3307`, user `absen_solat`, password sesuai
`DB_PASSWORD_DOCKER`, dan database `absen_solat`.

Backup dan restore database:

```sh
docker compose exec db mysqldump -u root -p absen_solat > backup-absen-solat.sql
docker compose exec -T db mysql -u root -p absen_solat < backup-absen-solat.sql
```

Bila database hanya perlu diakses dari komputer server sendiri, isi
`DB_PUBLISH_HOST=127.0.0.1` di `.env` lalu jalankan
`docker compose up -d`. Dengan begitu port MySQL tidak terbuka ke jaringan.

Catatan keamanan: `MYSQL_ROOT_HOST` bawaan compose adalah `%`, artinya akun
root boleh masuk dari luar container. Nilai itu hanya terpakai saat volume
database masih kosong. Perketat dengan password root yang kuat, atau batasi akses
lewat `DB_PUBLISH_HOST`, atau ubah `MYSQL_ROOT_HOST` menjadi `127.0.0.1`
sebelum menjalankan `docker compose up` pertama kali.

## Memakai MySQL yang sudah ada

Aplikasi tidak harus memakai MySQL bawaan compose. Cukup ubah tiga kunci di
`.env`:

```env
DB_HOST_DOCKER=host.docker.internal
DB_USERNAME_DOCKER=absen_solat
DB_PASSWORD_DOCKER=password-di-mysql-anda
```

`host.docker.internal` menunjuk komputer host, sesuai untuk MySQL Laragon
atau XAMPP yang sudah berjalan di komputer yang sama. Untuk MySQL di komputer
lain, isi alamat IP-nya, misalnya `192.168.1.20`.

Perlu diketahui: `DB_PASSWORD_DOCKER` **tidak boleh kosong**. Compose membaca
seluruh file lebih dulu, jadi file ditolak dengan pesan
`required variable DB_PASSWORD_DOCKER is missing a value` walaupun Anda hanya
menjalankan layanan `app`, `web`, `queue`, dan `scheduler` tanpa `db`. Karena
bawaan MySQL Laragon sering tanpa password root, buatkan satu user khusus di
MySQL komputer host:

```sql
CREATE DATABASE IF NOT EXISTS absen_solat
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'absen_solat'@'%' IDENTIFIED BY 'AbsenSolat2026';
GRANT ALL PRIVILEGES ON absen_solat.* TO 'absen_solat'@'%';
FLUSH PRIVILEGES;
```

Akun `'absen_solat'@'%'` dipakai karena akun `root@localhost` milik Laragon
tidak cocok untuk sambungan yang datang dari alamat jaringan Docker, dan
hasilnya hanya `Access denied for user 'root'@'172.x.x.x'`. Setelah user
itu ada, isi `.env`:

```env
DB_HOST_DOCKER=host.docker.internal
DB_USERNAME_DOCKER=absen_solat
DB_PASSWORD_DOCKER=AbsenSolat2026
```

Pastikan akun MySQL tersebut boleh masuk dari luar komputer database
(`'absen_solat'@'%'`) dan databasenya sudah dibuat. Jalankan migrasi sendiri
bila perlu:

```sh
docker compose exec app php artisan migrate --force
```

Bila tidak ingin MySQL bawaan compose ikut berjalan, sebutkan layanannya
secara tersendiri:

```sh
docker compose up -d --build app web queue scheduler
```

Dengan cara ini database yang dipakai aplikasi adalah database MySQL di
komputer host, bukan database di dalam container, sehingga data yang diisi lewat
aplikasi langsung terlihat di HeidiSQL atau Laragon. Sebaliknya, bila
`DB_HOST_DOCKER=db` dibiarkan, data tersimpan di dalam volume Docker
`absen-solat-dbdata` dan terpisah dari database komputer host.

## Mesin pengenal wajah

Mesin wajah berjalan terpisah dari aplikasi ini (layanan Python). Alamat yang
dipakai dari dalam container diatur lewat `FACE_SERVICE_URL_DOCKER` di `.env`.

Dua hal yang sering menjadi penyebab mesin wajah tidak bisa dihubungi:

1. `127.0.0.1` di dalam container menunjuk container itu sendiri, bukan komputer
   induk. Gunakan `http://host.docker.internal:5005` untuk mesin wajah di
   komputer yang sama, atau alamat IP komputer tersebut.
2. Mesin wajah yang berjalan di komputer host harus listen di semua
   interface, bukan hanya `127.0.0.1`, sebab sambungan dari container datang dari
   interface jaringan Docker. Jalankan dengan `FACE_HOST` diisi `0.0.0.0`:

```powershell
$env:FACE_HOST = '0.0.0.0'
.\.venv\Scripts\python.exe face_service.py
```

Salah satu tandanya adalah panel queue di halaman Wajah berhenti di tengah
jalan dan log container `queue` berisi pesan mesin wajah tidak bisa
dihubungi.

## Perintah sehari-hari

| Keperluan                                | Perintah                                             |
| ---------------------------------------- | ---------------------------------------------------- |
| Melihat log aplikasi                     | `docker compose logs -f app`                         |
| Melihat log worker queue                 | `docker compose logs -f queue`                       |
| Melihat log web                          | `docker compose logs -f web`                         |
| Masuk ke container aplikasi              | `docker compose exec app sh`                         |
| Menjalankan perintah artisan             | `docker compose exec app php artisan migrate:status` |
| Menyalakan ulang setelah mengubah kode   | `docker compose up -d --build`                       |
| Menyalakan ulang setelah mengubah `.env` | `docker compose up -d --force-recreate`              |
| Menghentikan tanpa menghapus data        | `docker compose stop`                                |
| Mematikan dan menghapus container        | `docker compose down`                                |
| Menghapus container beserta seluruh data | `docker compose down -v`                             |

Kode aplikasi menyatu di dalam image. Setelah mengubah file PHP, JavaScript,
atau Blade, jalankan `docker compose up -d --build` supaya perubahannya ikut.
File yang sudah dikompilasi bisa dibersihkan dengan
`docker compose exec app php artisan optimize:clear`.

## Kalau ada masalah

| Gejala                                                    | Sebab dan penanganan                                                                                                                                                                                                                                                                                                                                                                                      |
| --------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Halaman putih atau error 500                              | Buka `docker compose logs app`. Isi `APP_DEBUG_DOCKER=true` di `.env` lalu jalankan ulang untuk melihat pesan terperinci, dan kembalikan ke `false` setelah selesai                                                                                                                                                                                                                                       |
| Error 419 Page Expired                                    | Sesi gagal disimpan. Pastikan migrasi sudah jalan (`docker compose exec app php artisan migrate:status`) sehingga tabel `sessions` ada, dan `APP_KEY` di `.env` tidak berubah dari sebelumnya. Semua sesi lama juga batal bila `APP_KEY` diganti                                                                                                                                                          |
| Masuk berhasil tetapi langsung kembali ke halaman masuk   | Sesi tidak tersimpan. Periksa log container `app` dan pastikan waktu di komputer server benar                                                                                                                                                                                                                                                                                                             |
| Error 502 Bad Gateway                                     | Container `app` belum siap atau sedang dimulai. Lihat `docker compose ps` dan `docker compose logs app`                                                                                                                                                                                                                                                                                                   |
| Error 504 atau halaman berhenti saat upload               | Batas waktu nginx dan batas ukuran upload. Nilai bawaannya sudah berjenjang: nginx 96 MB, `post_max_size` 64 MB, satu foto 5 MB                                                                                                                                                                                                                                                                           |
| Kartu kemajuan upload wajah berhenti                      | Container `queue` mati atau mesin wajah tidak bisa dihubungi. Periksa `docker compose ps` dan `docker compose logs queue`                                                                                                                                                                                                                                                                                 |
| Access denied for user                                    | Dua sebab. Bila `DB_HOST_DOCKER=db`: password di `DB_PASSWORD_DOCKER` tidak sama dengan password yang sudah tersimpan di volume MySQL, jadi kembalikan password lama atau hapus volume dengan `docker compose down -v` bila datanya boleh hilang. Bila memakai MySQL komputer host: akun `root@localhost` tidak cocok untuk sambungan dari alamat jaringan Docker sehingga perlu user `'absen_solat'@'%'` |
| `required variable DB_PASSWORD_DOCKER is missing a value` | `DB_PASSWORD_DOCKER` atau `DB_ROOT_PASSWORD` masih kosong di `.env`. Compose menolak seluruh file walaupun layanan `db` tidak ikut dijalankan, jadi isi kedua kunci itu dengan password yang sah                                                                                                                                                                                                          |
| Port sudah dipakai                                        | Ubah `APP_PORT` atau `DB_PUBLISH_PORT` di `.env`, lalu `docker compose up -d`                                                                                                                                                                                                                                                                                                                             |
| Koneksi ke MySQL lambat atau gagal berulang               | Pastikan volume database `absen-solat-dbdata` masih ada (`docker volume ls`) dan `docker compose logs db` tidak ada error                                                                                                                                                                                                                                                                                 |
| Build berhenti pada pemeriksaan versi PHP                 | Image PHP yang dipakai lebih tua dari 8.4.1. Jangan turunkan image; naikkan versinya                                                                                                                                                                                                                                                                                                                      |

Perubahan besar pada `docker-compose.yml` atau `Dockerfile` bisa diperiksa
tanpa menjalankan apa pun:

```sh
docker compose config --quiet
```

## Catatan teknis yang perlu diketahui

1. Image PHP memakai 8.5. `composer.json` menulis `^8.3`, tetapi yang
   menentukan sesungguhnya adalah `composer.lock`: file
   `vendor/composer/platform_check.php` menuntut PHP 8.4.1 atau lebih baru.
   Pada proyek lain, memakai image PHP yang lebih tua membuat container `queue`
   dan `scheduler` mati berulang dengan kode 255, sementara container `app` tetap
   tertulis sehat sebab health check-nya hanya membuka socket FastCGI.
   Karena itu di sini ada dua penjaga: pemeriksaan versi saat build, dan
   health check yang benar-benar menjalankan Laravel.
2. `opcache`, `pdo_sqlite`, dan `sqlite3` tidak diminta lewat
   `docker-php-ext-install` sebab ketiganya sudah menyatu di image resmi PHP.
   Memintanya membuat build gagal dengan kode keluar 2. Yang dipasang
   hanya `pdo_mysql`, `pcntl`, dan bila perlu `mbstring` serta `curl`.
3. nginx hanya listen IPv4. Pendengar IPv6 pernah membuat nginx gagal
   hidup dengan error alamat keluarga tidak didukung pada mesin tanpa IPv6.
4. Nama layanan `app` dicari lewat resolver Docker pada setiap permintaan,
   bukan diingat sekali. Tanpa itu, setelah container `app` dibangun ulang nginx
   menembak alamat lama dan semua halaman menjadi 502.
5. Folder `storage` disimpan sebagai volume karena foto wajah yang menunggu
   diubah berada di `storage/app/private`. Tanpa volume, container web dan container
   worker queue memakai folder berbeda dan upload hilang tanpa jejak.
6. Migrasi berjalan sendiri saat container `app` hidup, tetapi seeding tidak.
   Seeder menimpa waktu solat bawaan, jadi jalankan hanya pada database
   yang masih kosong.
7. Log aplikasi dikirim ke output container (`LOG_CHANNEL=stderr`) supaya
   terbaca dengan `docker compose logs` dan tidak menumpuk di dalam volume.

## File terkait

- `README.md`, pengenalan aplikasi dan cara menjalankan tanpa Docker.
- `config/face.php` beserta blok `FACE_*` di `.env`, pengaturan mesin wajah.
- Mesin pengenal wajah, layanan Python terpisah yang tidak ikut dijalankan
  oleh compose ini dan cukup dihubungi lewat alamat di `.env`.
