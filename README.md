# Absen Solat SMKIM4

Aplikasi web pencatat kehadiran solat di sekolah. Ada dua cara mencatat
kehadiran dan keduanya bisa dipakai bersamaan:

- Absen wajah, jamaah menghadap kamera dan wajahnya dicocokkan dengan foto
  yang sudah didaftarkan.
- Absen sensor, perangkat di gerbang mengirim data ke alamat API aplikasi.

Aplikasi memakai Laravel 13, database MySQL, dan tampilan Tailwind yang
dibangun Vite. Pengenalan wajah dikerjakan layanan Python terpisah, jadi
aplikasi tetap bisa dijalankan tanpa layanan itu. Yang tertunda hanya
pemrosesan foto wajah.

Setiap absen wajah menyimpan satu foto potret jamaah sebagai bukti. Fotonya
dikecilkan oleh layanan Python sebelum dikirim, lalu disimpan aplikasi dan
bisa dilihat di riwayat absen.

Panduan di bawah ditulis berurutan supaya aplikasi benar-benar jalan di
komputer baru. Ada dua cara menjalankannya: langsung di komputer sendiri,
atau lewat Docker. Cara pertama lebih cepat untuk mencoba, cara kedua lebih
rapi untuk dipasang di server sekolah.

## Halaman menurut peran

| Peran            | Halaman                                                              |
| ---------------- | -------------------------------------------------------------------- |
| Administrator    | Ringkasan, Anggota, Waktu Solat, Sesi Absen, Rekap, Perangkat, Wajah |
| Petugas          | Ringkasan, Sesi Absen, Rekap, Absen Wajah                            |
| Murid            | Riwayat absen sendiri                                                |
| Perangkat sensor | API absen solat dan API pendaftaran wajah                            |

Semua peran masuk lewat halaman `/masuk`. Akun yang belum pernah mengganti
password langsung diarahkan ke `/ganti-password`.

## Cara kerja sesi absen

Absen hanya diterima selama sesi dibuka. Sesi dikelola sendiri oleh penjadwal:

- Sesi untuk hari ini dan besok disiapkan sekali sehari sesudah tengah malam,
  jadi daftar sesi sudah lengkap sebelum waktu solat pertama dimulai.
- Sesi dibuka sepuluh menit sebelum jam mulai, lalu ditutup di akhir jendela.
  Angka menitnya bisa diubah lewat `SOLAT_EARLY_OPEN_MINUTES`.
- Bila masih ada sesi yang terbuka, sesi berikutnya ditandai Terjadwal dan
  terbuka sendiri begitu sesi sebelumnya ditutup.
- Sesi yang tertinggal dari hari sebelumnya ditutup lebih dulu supaya tidak
  menghalangi sesi hari ini.
- Bila sesi dibuka atau ditutup sendiri oleh petugas, keputusan petugas selalu
  menang dan mesin tidak mengubahnya.

Tombol Buka dan Tutup di halaman Sesi Absen tetap ada untuk keadaan yang perlu
dibuka lebih dulu. Karena itu penjadwal wajib hidup: `php artisan schedule:work`
di komputer sendiri, atau layanan `scheduler` bila memakai Docker.

## Menghapus sesi

Sesi yang salah dibuat bisa dibersihkan admin dari halaman Sesi Absen dalam
tiga cara, semuanya ikut menghapus catatan kehadiran beserta foto buktinya:

| Cara                    | Yang terhapus                                      |
| ----------------------- | -------------------------------------------------- |
| Tombol Hapus pada baris | Satu sesi saja                                     |
| Hapus yang dicentang    | Sesi yang kotaknya dicentang, bisa lebih dari satu |
| Hapus sesi tanggal ini  | Seluruh sesi pada tanggal yang sedang dilihat      |

Sesi yang sedang dibuka tidak ikut terhapus dan pesannya menyebutkan hal itu,
karena catatan yang baru saja ditulis alat tidak boleh hilang begitu saja.
Tutup dulu sesi tersebut, baru hapus.

## Mode uji coba absen

Menguji kamera absen tidak lagi menuntut sesi solat yang sungguhan dibuka.
Admin menyalakan Mode uji coba absen di halaman Sesi Absen, lalu satu sesi
khusus bernama Uji coba terbuka. Selama mode itu menyala:

- Petugas dan anggota yang ditugaskan memindai wajah boleh ikut mencoba.
- Halaman Absen Wajah menampilkan pemberitahuan bahwa mode uji coba sedang
  menyala, jadi tidak ada yang salah paham.
- Orang yang sama baru bisa dipindai lagi setelah empat menit, jeda itu diatur
  lewat `SOLAT_TEST_COOLDOWN_MINUTES`.
- Hasil percobaan tidak dihitung di rekap, riwayat, maupun ringkasan.

Tombol Hapus sesi uji coba membersihkan sesi percobaan itu beserta catatan dan
foto buktinya, jadi tidak ada sisa data uji yang menumpuk.

## Bukti foto absen wajah

Catatan absen wajah menyimpan satu foto potret jamaah sebagai bukti. Fotonya
datang bersama hasil pengenalan wajah dari layanan Python, ukurannya sudah
dikecilkan di sana, dan aplikasi hanya menyimpannya.

| Hal                | Keterangan                                                      |
| ------------------ | --------------------------------------------------------------- |
| Tempat penyimpanan | Folder privat `storage/app/private/bukti-absen/tahun/bulan`     |
| Ukuran berkas      | Di bawah 50 KB, umumnya sekitar 13 KB pada potret 320 kali 480  |
| Yang boleh membuka | Admin, petugas, dan pemilik catatannya sendiri                  |
| Tempat melihat     | Riwayat absen murid, Sesi Absen, dan log di halaman Absen Wajah |
| Pembersihan        | `solat:bersihkan-bukti` setiap hari pukul 03.00, bawaan 90 hari |

Pengaturan yang menaunginya ada di bagian `Bukti foto absen wajah` pada
`.env`.

```env
SOLAT_KEEP_PROOF_PHOTO=true
SOLAT_PROOF_MAX_BYTES=262144
SOLAT_PROOF_RETENTION_DAYS=90
```

Isi `SOLAT_KEEP_PROOF_PHOTO=false` bila foto bukti tidak diinginkan, dan isi
`SOLAT_PROOF_RETENTION_DAYS=0` bila foto ingin disimpan selamanya. Foto yang
gagal diambil, terlalu besar, atau bukan berkas JPEG tidak menghalangi absen:
catatannya tetap tersimpan, hanya tanpa bukti. Bentuk fotonya diatur di sisi
layanan Python lewat `FACE_PROOF_MAX_SIDE`, `FACE_PROOF_QUALITY`, dan
`FACE_PROOF_MAX_KB`, jadi citra wajahnya tidak perlu diolah lagi oleh
PHP yang tidak memuat pustaka gambar.

## Yang harus tersedia

| Perkakas     | Versi                                | Catatan                                                               |
| ------------ | ------------------------------------ | --------------------------------------------------------------------- |
| PHP          | 8.4.1 atau lebih baru                | `composer.json` menulis `^8.3`, tetapi `composer.lock` menuntut 8.4.1 |
| Composer     | 2.x                                  | Pemasang paket PHP                                                    |
| Node.js      | 20.19 atau lebih baru, disarankan 24 | Vite 8 dipakai untuk membangun tampilan                               |
| MySQL        | 8.x                                  | Server database. XAMPP, Laragon, atau MySQL resmi                     |
| Ekstensi PHP | `pdo_mysql`, `mbstring`, `curl`      | Diperiksa dengan `php -m`                                             |

Pemeriksaan cepat sebelum mulai:

```sh
php -v
php -m
composer -V
node -v
npm -v
```

Di Windows, PHP sering tidak ada di `PATH`. Contoh di komputer yang PHP-nya
dari paket php.new, semua perintah `php` ditulis dengan alamat lengkap:

```powershell
C:\php8\php.exe artisan serve --port=8000
```

## Cara tercepat

Bila databasenya sudah dibuat dan file `.env` sudah diisi, satu perintah
ini mengerjakan hampir semuanya: memasang paket PHP, membuat `.env`,
membangkitkan `APP_KEY`, menjalankan migrasi, memasang paket Node, lalu
membangun tampilan.

```sh
composer setup
```

Dua hal yang tidak dikerjakan perintah di atas: membuat databasenya, dan
mengisi data awal. Keduanya dijelaskan di langkah 4 dan langkah 5.

## Cara 1: menjalankan di komputer sendiri

Cara ini memakai `php artisan serve`, paling cepat untuk mencoba aplikasi.

### Langkah 1: ambil kode dan pasang paket PHP

```sh
git clone <alamat repositori> absen_solat
cd absen_solat
composer install
```

### Langkah 2: siapkan file .env

Salin contohnya, lalu bangkitkan kunci aplikasi. `APP_KEY` wajib ada, kalau
kosong sesi tidak bisa disimpan dan muncul error 419 Page Expired.

```sh
copy .env.example .env
```

Di Linux atau macOS perintahnya `cp .env.example .env`. Setelah itu:

```sh
php artisan key:generate
```

Periksa bagian ini di dalam `.env`. Sesuaikan user dan password MySQL
dengan komputer yang dipakai.

```env
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=absen_solat
DB_USERNAME=root
DB_PASSWORD=
```

Laragon dan XAMPP biasanya memakai user `root` tanpa password. Bila MySQL
memakai password, isi juga `DB_PASSWORD`.

### Langkah 3: sesuaikan aturan sesi absen

Bagian `Aturan absen solat` di dalam `.env` menentukan kapan sesi disiapkan,
dibuka, dan ditutup. Nilai bawaannya sudah cocok untuk pemakaian biasa.

```env
SOLAT_LATE_MINUTES=10
SOLAT_DEVICE_ID=kamera-wajah
SOLAT_EARLY_OPEN_MINUTES=10
SOLAT_SESSION_CREATE_TIME=00:05
SOLAT_PREPARE_DAYS_AHEAD=1
SOLAT_TEST_COOLDOWN_MINUTES=4
```

| Kunci                         | Arti                                                                           |
| ----------------------------- | ------------------------------------------------------------------------------ |
| `SOLAT_LATE_MINUTES`          | Menit toleransi sebelum absen dicatat terlambat                                |
| `SOLAT_DEVICE_ID`             | Kode perangkat kamera absen bawaan                                             |
| `SOLAT_EARLY_OPEN_MINUTES`    | Menit sebelum jam mulai saat sesi sudah boleh dibuka petugas maupun mesin      |
| `SOLAT_SESSION_CREATE_TIME`   | Jam penjadwal menyiapkan sesi hari itu                                         |
| `SOLAT_PREPARE_DAYS_AHEAD`    | Berapa hari sesudah hari ini ikut disiapkan sesinya, isi 0 bila hanya hari ini |
| `SOLAT_TEST_COOLDOWN_MINUTES` | Jeda menit sebelum orang yang sama boleh dipindai lagi di mode uji coba        |

Kunci yang sama juga berlaku di dalam Docker karena `docker-compose.yml` membaca
berkas `.env`, jadi tidak perlu diatur dua kali. Daftar kunci khusus Docker ada
di `README-docker.md` dan di komentar paling atas `docker-compose.yml`.

### Langkah 4: buat databasenya

Laravel tidak membuat databasenya sendiri, jadi langkah ini dikerjakan
sekali. Di Laragon, klik kanan lalu pilih Database dan buat database
bernama `absen_solat`. Lewat terminal:

```sh
mysql -u root -p -e "CREATE DATABASE absen_solat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Muatan `utf8mb4` dipilih supaya nama dan catatan berbahasa Indonesia
tersimpan utuh, dan sama dengan penyetelan image MySQL pada
`docker-compose.yml`.

### Langkah 5: jalankan migrasi dan isi data awal

```sh
php artisan migrate --seed
```

Perintah itu membuat semua tabel, lalu mengisi waktu solat, perangkat kamera
bawaan, dan beberapa akun contoh. Bila hanya ingin tabelnya saja, jalankan
`php artisan migrate` dan lakukan seeding kapan saja dengan
`php artisan db:seed`.

Perhatian: seeding tidak boleh dijalankan pada database yang sudah
dipakai, karena password akun bawaan ikut ditulis ulang.

### Langkah 6: pasang paket tampilan dan bangun aset

```sh
npm install
npm run build
```

Tanpa langkah ini halaman akan terbuka tanpa gaya dan tanpa skrip, sebab
tampilan dilayani file hasil build.

### Langkah 7: jalankan aplikasinya

```sh
php artisan serve --port=8000
```

Untuk pekerjaan sehari-hari, satu perintah ini menjalankan tiga proses
sekaligus: server PHP, pengolah queue, dan Vite dalam mode pantau.

```sh
composer dev
```

Bila queue dijalankan terpisah, perintahnya:

```sh
php artisan queue:work
```

Queue wajib hidup supaya upload foto wajah diproses di latar belakang.
Tanpa queue, foto tetap tersimpan tetapi berstatus menunggu.

Jadwal yang berjalan di penjadwal ada empat: penyiapan sesi absen harian sekali
sehari, pembukaan dan penutupan sesi absen setiap menit, pembuangan foto bukti
absen yang sudah lama, serta pembuangan file foto sementara. Jalankan dengan
`php artisan schedule:work`. Daftarnya bisa dilihat dengan
`php artisan schedule:list`.

Tanpa penjadwal, sesi absen tidak akan disiapkan, dibuka, maupun ditutup
sendiri. Absen masih bisa berjalan dengan menekan tombol Buka di halaman Sesi
Absen, dan sesi satu hari bisa disiapkan sekaligus lewat perintah:

```sh
php artisan solat:sesi-harian
php artisan solat:sesi-harian --date=2026-10-11 --days=2
```

### Langkah 8: buka dan masuk

Buka `http://localhost:8000`, halaman pertama yang muncul adalah halaman
masuk. Pakai akun bawaan di bawah, lalu ganti passwordnya bila diminta.

## Tanda aplikasi sudah jalan

Periksa berurutan dari atas. Bila satu langkah gagal, perbaikannya ada di
bagian "Kalau ada masalah".

| Pemeriksaan                  | Hasil yang benar                                      |
| ---------------------------- | ----------------------------------------------------- |
| `php artisan migrate:status` | Semua baris berstatus `Ran`                           |
| `php artisan about`          | Tidak ada error koneksi database                      |
| Buka `http://localhost:8000` | Halaman masuk muncul berikut gaya dan warnanya        |
| Masuk sebagai admin          | Halaman Ringkasan memuat kartu angka                  |
| Buka `/waktu-solat`          | Lima baris waktu solat sudah terisi                   |
| Buka `/perangkat`            | Perangkat `kamera-wajah` sudah terdaftar              |
| Buka `/anggota`              | Enam akun murid dan dua akun pengurus ada             |
| Buka `/sesi-absen`           | Sesi hari ini dan besok sudah terisi sendiri          |
| Jalankan penjadwal           | Sesi absen disiapkan, lalu dibuka dan ditutup sendiri |

API untuk alat bisa dicoba tanpa perangkat sungguhan. Balasan berikut muncul
ketika kode perangkat dikirim tanpa data lain.

```powershell
curl.exe -s -X POST http://127.0.0.1:8000/api/solat/scan -H "Content-Type: application/json" -d '{"device_id":"kamera-wajah"}'
```

Jawabannya berisi `"display_message":"Data alat kurang"` dengan kode HTTP 422.
Itu artinya rute API hidup dan pengaman perangkat bekerja. Alat yang belum
didaftarkan mendapat kode 403 dengan keterangan `Belum terdaftar`.

## Cara 2: menjalankan dengan Docker

Cara ini memasang aplikasi, MySQL, nginx, pengolah queue, dan penjadwal
sekaligus. Isi bagian `_DOCKER` di `.env` lebih dulu, terutama
`DB_PASSWORD_DOCKER` dan `DB_ROOT_PASSWORD` yang tidak boleh kosong.

```sh
docker compose up -d --build
docker compose ps
```

Setelah semua layanan berstatus `healthy`, buka `http://localhost:8080`. Bila
databasenya baru dan masih kosong, isi data awalnya sekali saja.

```sh
docker compose exec app php artisan db:seed --force
```

Aturan sesi absen di bagian `Aturan absen solat` pada `.env` tidak perlu diubah
lagi untuk Docker. Berkas itu dibaca langsung oleh `docker-compose.yml` dan
diserahkan ke layanan `app`, `queue`, dan `scheduler`.

Panduan lengkapnya, termasuk mengganti port, membuka MySQL dari komputer
lain, dan memakai MySQL yang sudah ada di komputer host, ada di
[`README-docker.md`](README-docker.md).

## Akun bawaan

Akun ini dibuat oleh seeder data. Ganti passwordnya sebelum dipakai di
lingkungan sekolah.

| Peran   | Email                                                 | Password                      |
| ------- | ----------------------------------------------------- | ----------------------------- |
| Admin   | `admin@smkistiqomah.sch.id`                           | `admin123`                    |
| Petugas | `petugas@smkistiqomah.sch.id`                         | `petugas123`                  |
| Murid   | `ahmad.fauzi@smkistiqomah.sch.id` dan lima murid lain | Nomor induk, misalnya `25001` |

Akun murid memakai nomor induk sebagai password awal dan wajib diganti saat
pertama masuk. Akun petugas ditandai sebagai petugas kamera, jadi bisa
membuka halaman Absen Wajah.

## Mesin pengenal wajah

Bagian ini opsional. Alamat layanan diatur di `.env`, dan aplikasi tetap
jalan walaupun layanan ini mati.

```env
FACE_SERVICE_URL=http://127.0.0.1:5005
FACE_TOLERANCE=0.5
FACE_AGGREGATE_PHOTOS=2
FACE_SINGLE_PHOTO_TOLERANCE=0.45
FACE_MIN_MARGIN=0.06
FACE_MIN_FACE_SIZE=80
FACE_ENFORCE_MODEL=true
FACE_SERVICE_TIMEOUT=90
FACE_EXPECTED_SECONDS=15
```

Mesin wajah adalah layanan Python terpisah yang berada di repositori lain.
Setelah layanannya hidup, periksa sambungannya:

```sh
curl http://127.0.0.1:5005/health
```

Alur pemakaiannya:

1. Daftarkan wajah anggota di halaman `/wajah`, bisa satu per satu atau
   sekaligus beberapa foto. Maksimal 20 foto per anggota.
2. Tandai petugas kamera di halaman yang sama.
3. Petugas membuka `/absen-wajah` dari ponsel atau komputer berkamera, lalu
   jepretan dikirim ke layanan wajah dan kehadirannya tercatat.

### Cara identitas diputuskan

Mesin wajah mengubah wajah menjadi 128 angka, dan angka itulah yang dibandingkan.
Yang paling dekat belum tentu orang yang sama, jadi keputusannya diambil berlapis:

| Lapis                       | Aturannya                                                                                     |
| --------------------------- | --------------------------------------------------------------------------------------------- |
| Jarak per foto              | Jarak dihitung ke setiap foto anggota, lalu dikelompokkan per anggota                         |
| Jarak satu anggota          | Rata-rata dua jarak terdekatnya (`FACE_AGGREGATE_PHOTOS`), supaya satu foto buruk tidak cukup |
| Penerimaan                  | Jarak itu wajib di bawah `FACE_TOLERANCE`                                                     |
| Anggota berfoto tunggal     | Batasnya lebih ketat, `FACE_SINGLE_PHOTO_TOLERANCE`, karena tidak ada foto pendukung          |
| Dua anggota sama-sama dekat | Hasilnya dilaporkan ambigu bila selisihnya di bawah `FACE_MIN_MARGIN`                         |
| Wajah terlalu kecil         | Ditolak bila sisi wajah kurang dari `FACE_MIN_FACE_SIZE` piksel                               |
| Model berbeda               | Angka wajah dari model lain dilewati selama `FACE_ENFORCE_MODEL` menyala                      |

Balasan absen karena itu bisa berupa `recorded`, `late`, atau `duplicate` yang
artinya kehadiran tercatat, dan `unknown`, `ambiguous`, `no_face`,
`face_too_small`, `crowded`, `unavailable`, atau `invalid` yang artinya tidak ada
kehadiran yang tersimpan.

Angka bawaan di atas berasal dari pengukuran foto sungguhan di satu mesin: wajah
orang yang berbeda berjarak 0,53 sampai 0,71, sedangkan wajah orang yang sama pada
potret yang layak berada di bawah 0,5. Angka itu wajib diukur ulang memakai foto
dari sekolah sendiri sebelum dipakai untuk kehadiran yang dihitung, sebab
ketajaman mesin wajah tidak bisa dipastikan dari contoh kecil.

Dua sampai tiga foto per anggota sangat dianjurkan. Anggota berfoto tunggal
diukur dengan batas yang lebih ketat, sehingga orang yang sebenarnya cocok bisa
saja ditolak dan diminta memindai ulang pada cahaya yang lebih baik. Halaman
`/wajah` ikut menjaga mutunya: foto yang berbeda jauh dari foto lain milik anggota
yang sama ditandai supaya diganti, dan anggota yang fotonya masih sedikit diberi
keterangan untuk ditambah.

Sekalian dengan pengenalan wajah, layanan Python mengirim potret kecil yang
versinya sudah dikecilkan. Aplikasi meminta potret itu dengan menambahkan
`?with_photo=1` pada permintaan ke `/encode`, jadi mesin wajah versi lama yang
belum mengenal permintaan itu tetap bisa dipakai, hanya fotonya yang kosong.

Satu foto butuh sekitar 15 detik pada komputer tanpa GPU, karena
itu upload masuk queue dan kemajuannya bisa dilihat di panel Proses
pendaftaran wajah. Bila mesin wajah mati, foto tetap masuk queue dengan
status `service_unavailable` dan bisa diulang setelah layanannya hidup.
Kecilkan `FACE_EXPECTED_SECONDS` bila mesin wajahnya memakai GPU
supaya persen kemajuannya tidak terlalu cepat mentok di angka 100.

## Kalau ada masalah

| Gejala                                                | Perbaikan                                                                                                             |
| ----------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------- |
| `could not find driver`                               | Ekstensi `pdo_mysql` belum aktif. Hidupkan di `php.ini` lalu jalankan ulang `php -m`                                  |
| `Unknown database 'absen_solat'`                      | Databasenya belum dibuat. Kerjakan langkah 4                                                                          |
| `Access denied for user 'root'@'localhost'`           | `DB_USERNAME` atau `DB_PASSWORD` tidak cocok dengan MySQL yang dipakai                                                |
| Halaman terbuka tanpa gaya dan tanpa warna            | Aset belum dibangun. Jalankan `npm install` lalu `npm run build`                                                      |
| `Vite manifest not found`                             | Sama seperti di atas, file hasil build belum ada                                                                      |
| Error 419 Page Expired                                | `APP_KEY` kosong atau berubah. Jalankan `php artisan key:generate` lalu `php artisan config:clear`                    |
| Masuk berhasil lalu langsung kembali ke halaman masuk | Sesi tidak tersimpan. Pastikan `SESSION_DRIVER=database` dan migrasi sudah jalan, lalu periksa jam komputer           |
| Kolom Bukti menulis Tanpa bukti                       | Mesin wajah belum diperbarui atau `SOLAT_KEEP_PROOF_PHOTO=false`. Absen lama yang belum punya foto juga tampil begitu |
| Halaman muncul tetapi semua daftar kosong             | `php artisan migrate --seed` belum dijalankan, atau hanya migrasinya saja                                             |
| Unggah foto wajah gagal setelah menunggu lama         | Mesin wajah lambat atau mati. Periksa `FACE_SERVICE_URL`, lalu naikkan `FACE_SERVICE_TIMEOUT` bila memang lambat      |
| Pesan berhasil hilang sebelum sempat dibaca           | Halaman menyegarkan dirinya sendiri. Muat ulang halaman untuk melihat riwayatnya                                      |
| Perubahan `.env` tidak terasa                         | Jalankan `php artisan config:clear`, sebab konfigurasi bisa tersimpan di cache                                        |

## Pengujian

```sh
php artisan test
vendor/bin/pint --format agent
```

Perintah pertama menjalankan seluruh tes dengan database sementara di
memori, jadi database aslinya tidak tersentuh. Perintah kedua merapikan
gaya penulisan kode PHP.

## Struktur folder

```
app/
  Console/Commands/     perintah artisan: solat:sesi-harian, solat:sesi-otomatis,
                        solat:bersihkan-bukti, face:purge-uploads
  Http/Controllers/     halaman web dan alamat API
  Http/Middleware/      pemeriksaan peran, petugas kamera, ganti password wajib
  Jobs/                 pekerjaan queue, misalnya pemrosesan foto wajah
  Models/               model database
  Services/             wajah, perangkat, sesi absen, pencatat absen, rekap
  Support/              menu, label status, balasan untuk perangkat
database/
  migrations/           tabel aplikasi dan tabel bawaan Laravel
  seeders/              seeder akun, waktu solat, dan perangkat
docker/                 file untuk Docker: nginx, php.ini, entrypoint
resources/
  css/app.css           gaya tampilan
  js/app.js             skrip halaman, termasuk panel kemajuan queue
  views/                layout, halaman, dan potongan tampilan
routes/web.php          seluruh rute web dan API
routes/console.php      jadwal penjadwal: sesi otomatis tiap menit, pembersihan harian
config/solat.php        waktu solat bawaan, toleransi terlambat, daftar peran,
                        pengaturan foto bukti absen
config/face.php         alamat, toleransi, dan batas layanan wajah
storage/app/private/    berkas privat, termasuk foto bukti absen wajah
```

## File terkait

- `README-docker.md` untuk panduan Docker yang lebih terperinci.
- `.env.example` untuk seluruh kunci pengaturan beserta keterangannya.
- `Dockerfile` dan `docker-compose.yml` untuk pemasangan di server.
- Layanan pengenal wajah berada di repositori terpisah dan berjalan sebagai
  program Python sendiri.
