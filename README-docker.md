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

Layanan `scheduler` menjalankan `php artisan schedule:work` dan memegang empat
jadwal:

| Perintah                | Waktu        | Kegunaan                                     |
| ----------------------- | ------------ | -------------------------------------------- |
| `solat:sesi-harian`     | setiap hari  | Menyiapkan sesi hari ini dan hari berikutnya |
| `solat:sesi-otomatis`   | setiap menit | Membuka dan menutup sesi absen               |
| `solat:bersihkan-bukti` | 03.00        | Membuang foto bukti absen yang sudah lama    |
| `face:purge-uploads`    | setiap hari  | Membuang file foto sementara                 |

Jam `solat:sesi-harian` diambil dari `SOLAT_SESSION_CREATE_TIME` dan jumlah hari
sesudahnya dari `SOLAT_PREPARE_DAYS_AHEAD`. Biarkan layanan `scheduler` selalu
hidup supaya sesi absen selalu tersedia, terbuka, dan tertutup sendiri.

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

| Kunci                     | Arti                                                 | Bawaan                  |
| ------------------------- | ---------------------------------------------------- | ----------------------- |
| `APP_PORT`                | Port web di komputer host                            | `8080`                  |
| `APP_URL_DOCKER`          | Alamat aplikasi yang dilihat pengguna                | `http://localhost:8080` |
| `APP_ENV_DOCKER`          | Lingkungan aplikasi di dalam container               | `production`            |
| `APP_DEBUG_DOCKER`        | Tampilkan pesan error terperinci                     | `false`                 |
| `LOG_CHANNEL_DOCKER`      | Log aplikasi masuk ke output container               | `stderr`                |
| `LOG_LEVEL_DOCKER`        | Tingkat detail log                                   | `warning`               |
| `DB_HOST_DOCKER`          | Nama host MySQL yang dilihat dari dalam container    | `db`                    |
| `DB_USERNAME_DOCKER`      | User aplikasi di MySQL                               | `absen_solat`           |
| `DB_PASSWORD_DOCKER`      | Password user aplikasi, wajib diisi                  | `AbsenSolat2026`        |
| `DB_ROOT_PASSWORD`        | Password root MySQL bawaan compose, wajib diisi      | `RootAbsenSolat2026`    |
| `DB_PUBLISH_HOST`         | Interface jaringan tempat port MySQL dibuka          | `0.0.0.0`               |
| `DB_PUBLISH_PORT`         | Port MySQL untuk klien luar container                | `3307`                  |
| `RUN_MIGRATIONS`          | Jalankan migrasi saat container `app` hidup          | `true`                  |
| `FACE_SERVICE_URL_DOCKER` | Alamat mesin wajah yang dilihat dari dalam container | `http://wajah:5005`     |

Port web dipilih `8080` supaya tidak bentrok dengan `php artisan serve`
(8000) dan dengan XAMPP atau Laragon (80). Bila aplikasinya dibuka dari
komputer lain, isi `APP_URL_DOCKER` dengan alamat yang dipakai pengguna,
misalnya `http://192.168.1.10:8080`, agar tautan yang dibuat aplikasi ikut
tepat.

### Aturan sesi absen

Kunci di bawah ini ditulis tanpa akhiran `_DOCKER` karena dipakai bersama oleh
`php artisan serve` dan Docker. Karena `docker-compose.yml` memakai
`env_file: .env`, kuncinya otomatis ikut masuk ke layanan `app`, `queue`, dan
`scheduler`.

| Kunci                         | Arti                                                                   | Bawaan         |
| ----------------------------- | ---------------------------------------------------------------------- | -------------- |
| `SOLAT_LATE_MINUTES`          | Menit toleransi sebelum absen dicatat terlambat                        | `10`           |
| `SOLAT_DEVICE_ID`             | Kode perangkat kamera absen bawaan                                     | `kamera-wajah` |
| `SOLAT_EARLY_OPEN_MINUTES`    | Menit sebelum jam mulai saat sesi sudah boleh dibuka                   | `10`           |
| `SOLAT_SESSION_CREATE_TIME`   | Jam `solat:sesi-harian` menyiapkan sesi hari itu                       | `00:05`        |
| `SOLAT_PREPARE_DAYS_AHEAD`    | Berapa hari sesudah hari ini ikut disiapkan, `0` berarti hari ini saja | `1`            |
| `SOLAT_TEST_COOLDOWN_MINUTES` | Jeda menit sebelum orang yang sama dipindai lagi di mode uji coba      | `4`            |
| `SOLAT_KEEP_PROOF_PHOTO`      | Simpan foto bukti pada setiap absen wajah                              | `true`         |
| `SOLAT_PROOF_MAX_BYTES`       | Batas ukuran satu foto bukti yang diterima, dalam bita                 | `262144`       |
| `SOLAT_PROOF_RETENTION_DAYS`  | Umur foto bukti sebelum dibuang, `0` berarti disimpan selamanya        | `90`           |

Foto bukti absen wajah disimpan di dalam volume `absen-solat-storage` pada
folder `bukti-absen`. Volume itu wajib dipertahankan: `docker compose down -v`
menghapus seluruh foto bukti bersama datanya, sedangkan
`docker compose down` saja tidak.

Mengubah kuncinya cukup lewat `.env` lalu `docker compose up -d`; image tidak
perlu dibangun ulang. Untuk menyiapkan sesi di luar jadwal, misalnya saat
memasang aplikasi di tengah hari, jalankan sekali perintah berikut di dalam
container.

```sh
docker compose exec app php artisan solat:sesi-harian --days=2
```

### Ambang pengenalan wajah

Kunci berikut juga ditulis tanpa akhiran `_DOCKER` dan dipakai bersama oleh
`php artisan serve` serta layanan `app` dan `queue`. Alasan setiap angkanya
dijelaskan di `README.md` pada bagian Cara identitas diputuskan.

| Kunci                         | Arti                                                                | Bawaan           |
| ----------------------------- | ------------------------------------------------------------------- | ---------------- |
| `FACE_TOLERANCE`              | Jarak terjauh yang masih diterima sebagai anggota yang sama         | `0.5`            |
| `FACE_AGGREGATE_PHOTOS`       | Berapa jarak foto terdekat yang dirata-ratakan saat menilai anggota | `2`              |
| `FACE_SINGLE_PHOTO_TOLERANCE` | Batas lebih ketat bagi anggota yang fotonya kurang dari `2`         | `0.45`           |
| `FACE_MIN_MARGIN`             | Selisih minimal juara pertama dan kedua sebelum hasilnya ambigu     | `0.06`           |
| `FACE_MIN_FACE_SIZE`          | Sisi wajah paling kecil dalam piksel, `0` mematikan pemeriksaannya  | `80`             |
| `FACE_ENFORCE_MODEL`          | Lewati angka wajah yang dihitung oleh model lain                    | `true`           |
| `FACE_MODEL`                  | Nama model wajah yang dipakai dan dicatat pada setiap angka         | `dlib-resnet-v1` |
| `FACE_SERVICE_TIMEOUT`        | Batas tunggu satu foto ke mesin wajah, dalam detik                  | `90`             |

Mesin wajah versi lama tetap bisa dipakai: permintaan `require_single=1` diabaikan
oleh mesin yang belum mengenalnya, dan pemeriksaan jumlah wajah di foto tetap
dikerjakan di sisi aplikasi. Yang tidak berjalan pada mesin wajah versi lama
hanya pemeriksaan ukuran wajah, karena `face_size` belum dilaporkan.

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

Sekali saja, sebelum menjalankan Docker pertama kali, buat jaringan bersama
yang dipakai aplikasi ini dan mesin wajah. Mesin wajah sudah masuk ke jaringan
ini dengan nama `wajah`, jadi aplikasi cukup memanggil `http://wajah:5005`.

```sh
docker network create absen-jaringan
```

Jaringan itu sengaja dibuat di luar kedua berkas compose, sehingga
`docker compose down` di salah satu proyek tidak pernah menghapusnya.

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
dipakai dari dalam container diatur lewat `FACE_SERVICE_URL_DOCKER` di `.env`,
dan bawaannya sudah menunjuk mesin wajah sebagai container:

```env
FACE_SERVICE_URL_DOCKER=http://wajah:5005
```

Alamat itu bekerja karena aplikasi ini dan mesin wajah berada di satu jaringan
Docker bersama, `absen-jaringan`, dan mesin wajah masuk ke sana dengan nama
`wajah`. Jaringan itu termasuk yang dibuat di langkah memasang di atas.
Sambungannya langsung antar container, jadi port mesin wajah tidak perlu dibuka
ke komputer induk dan tidak ada lalu lintas foto yang melewati jaringan sekolah.

Karena itu layanan `app` dan `queue` disambungkan ke dua jaringan sekaligus:
`absen-solat` untuk berbicara dengan `db` dan `web`, serta `absen-jaringan`
untuk berbicara dengan mesin wajah.

### Bila mesin wajah dijalankan langsung di komputer, bukan di Docker

Jalankan mesin wajahnya dengan `FACE_HOST` diisi `0.0.0.0`, lalu ubah
`FACE_SERVICE_URL_DOCKER` menjadi `http://host.docker.internal:5005` dan
jalankan `docker compose up -d --force-recreate`.

```powershell
$env:FACE_HOST = '0.0.0.0'
.\.venv\Scripts\python.exe face_service.py
```

Alasan `FACE_HOST` harus `0.0.0.0`, bukan `127.0.0.1`: sambungan dari container
datang lewat alamat komputer induk, bukan lewat loopback komputer itu, sehingga
proses yang hanya mendengar di `127.0.0.1` tidak akan tersambung. Ini juga
sebabnya bawaan `FACE_HOST` di `face_service.py` sengaja `127.0.0.1`: layanan
itu tidak punya kata sandi, jadi terbuka ke jaringan hanya atas keputusan
sadar.

Salah satu tandanya adalah panel queue di halaman Wajah berhenti di tengah
jalan dan log container `queue` berisi pesan mesin wajah tidak bisa
dihubungi. Periksa dari dalam container:

```sh
docker compose exec app wget -qO- http://wajah:5005/health
```

Layanan Python itu juga yang menyiapkan foto bukti absen. Aplikasi meminta
potret kecil lewat permintaan `?with_photo=1`, lalu menyimpannya sendiri.
Bentuk fotonya diatur di sisi Python:

| Variabel lingkungan   | Arti                                                   | Bawaan |
| --------------------- | ------------------------------------------------------ | ------ |
| `FACE_PROOF_MAX_SIDE` | Sisi terpanjang foto bukti dalam piksel                | `480`  |
| `FACE_PROOF_QUALITY`  | Mutu JPEG, angka pertama yang dicoba                   | `72`   |
| `FACE_PROOF_MAX_KB`   | Ukuran terbesar yang diterima, sebelum dikecilkan lagi | `48`   |

Karena potretnya dibuat di sisi Python, citra PHP di sini tidak perlu memuat
pustaka gambar sama sekali. Bila mesin wajahnya dijalankan sendiri di luar
compose, image atau salinan layanannya wajib diperbarui setelah file
`face_service.py` berubah, sebab layanan lama menjawab tanpa potret dan
kolom Bukti pada riwayat absen akan menulis Tanpa bukti.

## Memakai domain dan HTTPS

Container `web` hanya melayani HTTP di dalam jaringan Docker. Sertifikat dan
nama domain dipegang oleh proxy di depannya, misalnya nginx, Caddy, Traefik,
atau Cloudflare. Susunannya seperti ini:

```text
peramban  ->  https://domain  ->  proxy pemegang sertifikat
                              ->  http://127.0.0.1:8080  ->  container web
```

Ada dua hal yang wajib dikerjakan supaya pengaturan ini tidak error.

**1. Proxy wajib mengirim header asal permintaan.** Tanpa header ini aplikasi
tidak tahu bahwa penggunanya datang lewat https. Pada nginx biasa tambahkan:

```nginx
proxy_set_header Host              $host;
proxy_set_header X-Real-IP         $remote_addr;
proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
proxy_set_header X-Forwarded-Proto $scheme;
```

Caddy, Traefik, dan Nginx Proxy Manager sudah mengirim ketiganya sendiri,
jadi tidak perlu ditambahkan.

**2. Isi `.env` seperti berikut.**

```env
APP_PORT=127.0.0.1:8080
APP_URL_DOCKER=https://domain-anda.sch.id
```

Angka pentingnya ada pada `X-Forwarded-Proto`. Aplikasi mempercayai header itu
hanya bila proxy-nya termasuk alamat yang dipercaya, dan daftar itu sudah diisi
di `bootstrap/app.php` untuk alamat jaringan lokal (`127.0.0.1`, `10.0.0.0/8`,
`172.16.0.0/12`, dan `192.168.0.0/16`). Bila proxy-nya ada di alamat lain,
tambahkan alamat itu ke daftar tersebut lalu jalankan
`docker compose up -d --build app web queue scheduler`.

`APP_PORT=127.0.0.1:8080` membuat port aplikasi hanya bisa dibuka dari
komputer server sendiri, sehingga tidak ada orang yang bisa melewati proxy.
Jaringan sekolah tetap bisa memakai aplikasi domain karena yang diakses
pengguna adalah port 443 milik proxy.

Akibat bila dua hal itu terlewat, gejalanya mudah dikenali: halaman depan
terbuka normal, panel yang menyegarkan diri sendiri juga jalan, tetapi tombol
kamera di halaman absen wajah berbunyi `Sambungan ke server terputus.`
Alasannya, aplikasi menulis alamat `http://domain` pada atribut `data-endpoint`,
sedangkan peramban menolak permintaan http dari halaman https.

Urutan memasangnya:

```sh
# 1. Proxy diarahkan ke 127.0.0.1:8080 dan sertifikatnya dipasang lebih dulu
# 2. Perbarui berkas aplikasi
cd /var/www/absen-solat
git pull
# 3. Bangun ulang, lalu pastikan kesehatan tiap layanan
sudo docker compose up -d --build app web queue scheduler
docker compose ps
# 4. Periksa alamat yang dibuat aplikasi, hasilnya harus diawali https
sudo docker compose exec app php artisan tinker --execute="echo route('attendance.wajah.scan');"
```

Langkah 4 adalah bukti tercepat. Hasil yang benar berbunyi
`https://domain-anda.sch.id/absen-wajah/pindai`. Bila masih `http://`, berarti
`APP_URL_DOCKER` belum terbaca atau headernya belum dikirim proxy.

Untuk memastikan dari sisi lain, buka halaman absen wajah di peramban,
tekan F12, lalu lihat tab Console. Bila muncul pesan `Mixed Content` yang
menyebut alamat `http://`, penyebabnya memang alamat yang dihasilkan aplikasi.

Bila aplikasi ternyata hanya dipakai lewat http di jaringan sekolah, tidak ada
yang perlu diubah pada bagian ini. Dua hal di atas hanya perlu saat memakai
domain dengan sertifikat.

## Perintah sehari-hari

| Keperluan                                | Perintah                                                    |
| ---------------------------------------- | ----------------------------------------------------------- |
| Melihat log aplikasi                     | `docker compose logs -f app`                                |
| Melihat log worker queue                 | `docker compose logs -f queue`                              |
| Melihat log penjadwal                    | `docker compose logs -f scheduler`                          |
| Melihat log web                          | `docker compose logs -f web`                                |
| Masuk ke container aplikasi              | `docker compose exec app sh`                                |
| Menjalankan perintah artisan             | `docker compose exec app php artisan migrate:status`        |
| Membuang foto bukti yang sudah lama      | `docker compose exec app php artisan solat:bersihkan-bukti` |
| Menyalakan ulang setelah mengubah kode   | `docker compose up -d --build`                              |
| Menyalakan ulang setelah mengubah `.env` | `docker compose up -d --force-recreate`                     |
| Menghentikan tanpa menghapus data        | `docker compose stop`                                       |
| Mematikan dan menghapus container        | `docker compose down`                                       |
| Menghapus container beserta seluruh data | `docker compose down -v`                                    |

Kode aplikasi menyatu di dalam image. Setelah mengubah file PHP, JavaScript,
atau Blade, jalankan `docker compose up -d --build` supaya perubahannya ikut.
File yang sudah dikompilasi bisa dibersihkan dengan
`docker compose exec app php artisan optimize:clear`.

## Kalau ada masalah

| Gejala                                                                | Sebab dan penanganan                                                                                                                                                                                                                                                                                                                                                                                      |
| --------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Halaman putih atau error 500                                          | Buka `docker compose logs app`. Isi `APP_DEBUG_DOCKER=true` di `.env` lalu jalankan ulang untuk melihat pesan terperinci, dan kembalikan ke `false` setelah selesai                                                                                                                                                                                                                                       |
| Error 419 Page Expired                                                | Sesi gagal disimpan. Pastikan migrasi sudah jalan (`docker compose exec app php artisan migrate:status`) sehingga tabel `sessions` ada, dan `APP_KEY` di `.env` tidak berubah dari sebelumnya. Semua sesi lama juga batal bila `APP_KEY` diganti                                                                                                                                                          |
| Masuk berhasil tetapi langsung kembali ke halaman masuk               | Sesi tidak tersimpan. Periksa log container `app` dan pastikan waktu di komputer server benar                                                                                                                                                                                                                                                                                                             |
| Error 502 Bad Gateway                                                 | Container `app` belum siap atau sedang dimulai. Lihat `docker compose ps` dan `docker compose logs app`                                                                                                                                                                                                                                                                                                   |
| Error 504 atau halaman berhenti saat upload                           | Batas waktu nginx dan batas ukuran upload. Nilai bawaannya sudah berjenjang: nginx 96 MB, `post_max_size` 64 MB, satu foto 5 MB                                                                                                                                                                                                                                                                           |
| Kartu kemajuan upload wajah berhenti                                  | Container `queue` mati atau mesin wajah tidak bisa dihubungi. Periksa `docker compose ps` dan `docker compose logs queue`                                                                                                                                                                                                                                                                                 |
| `network absen-jaringan declared as external, but could not be found` | Jaringan bersama belum dibuat, atau pernah terhapus. Jalankan `docker network create absen-jaringan`, lalu ulangi `docker compose up -d`                                                                                                                                                                                                                                                                  |
| Halaman Wajah menulis layanan wajah belum aktif                       | Mesin wajahnya belum hidup atau belum ikut ke jaringan bersama. Jalankan `docker compose up -d` di folder mesin wajah, lalu periksa dengan `docker compose exec app wget -qO- http://wajah:5005/health`                                                                                                                                                                                                   |
| Access denied for user                                                | Dua sebab. Bila `DB_HOST_DOCKER=db`: password di `DB_PASSWORD_DOCKER` tidak sama dengan password yang sudah tersimpan di volume MySQL, jadi kembalikan password lama atau hapus volume dengan `docker compose down -v` bila datanya boleh hilang. Bila memakai MySQL komputer host: akun `root@localhost` tidak cocok untuk sambungan dari alamat jaringan Docker sehingga perlu user `'absen_solat'@'%'` |
| `required variable DB_PASSWORD_DOCKER is missing a value`             | `DB_PASSWORD_DOCKER` atau `DB_ROOT_PASSWORD` masih kosong di `.env`. Compose menolak seluruh file walaupun layanan `db` tidak ikut dijalankan, jadi isi kedua kunci itu dengan password yang sah                                                                                                                                                                                                          |
| Port sudah dipakai                                                    | Ubah `APP_PORT` atau `DB_PUBLISH_PORT` di `.env`, lalu `docker compose up -d`                                                                                                                                                                                                                                                                                                                             |
| Tombol kamera berbunyi `Sambungan ke server terputus.`                | Aplikasi di belakang proxy pemegang sertifikat, tetapi proxy belum mengirim header `X-Forwarded-Proto` atau alamatnya belum terdaftar sebagai proxy yang dipercaya. Ikuti bagian **Memakai domain dan HTTPS** di atas                                                                                                                                                                                     |
| Koneksi ke MySQL lambat atau gagal berulang                           | Pastikan volume database `absen-solat-dbdata` masih ada (`docker volume ls`) dan `docker compose logs db` tidak ada error                                                                                                                                                                                                                                                                                 |
| Kolom Bukti pada riwayat menulis Tanpa bukti                          | Layanan wajah yang berjalan belum memuat pembuat potret, atau `SOLAT_KEEP_PROOF_PHOTO=false`. Perbarui layanan Python lalu nyalakan ulang, dan absen lama yang belum punya foto memang tampil begitu                                                                                                                                                                                                      |
| Build berhenti pada pemeriksaan versi PHP                             | Image PHP yang dipakai lebih tua dari 8.4.1. Jangan turunkan image; naikkan versinya                                                                                                                                                                                                                                                                                                                      |

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
   diubah dan foto bukti absen berada di `storage/app/private`. Tanpa volume,
   container web dan container worker queue memakai folder berbeda dan upload
   hilang tanpa jejak, sementara foto bukti hilang setiap container dibuat ulang.
6. Migrasi berjalan sendiri saat container `app` hidup, tetapi seeding tidak.
   Seeder menimpa waktu solat bawaan, jadi jalankan hanya pada database
   yang masih kosong.
7. Log aplikasi dikirim ke output container (`LOG_CHANNEL=stderr`) supaya
   terbaca dengan `docker compose logs` dan tidak menumpuk di dalam volume.
8. Sesi absen dibuka dan ditutup sendiri oleh layanan `scheduler` setiap menit,
   jadi absen tidak bergantung pada siapa yang mengingat menekan tombol Buka.
   Bila layanan itu mati, sesi tidak terbuka sendiri dan pesannya terlihat di
   `docker compose logs scheduler`. Tombol Buka di halaman Sesi Absen tetap bisa
   dipakai sebagai jalan darurat.

## File terkait

- `README.md`, pengenalan aplikasi dan cara menjalankan tanpa Docker.
- `config/face.php` beserta blok `FACE_*` di `.env`, pengaturan mesin wajah.
- Mesin pengenal wajah, layanan Python terpisah yang tidak ikut dijalankan
  oleh compose ini dan cukup dihubungi lewat alamat di `.env`.
