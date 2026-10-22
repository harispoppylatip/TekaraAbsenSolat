# RENCANA ANGGARAN BIAYA (RAB) & PROPOSAL PENGADAAN
# SISTEM ABSENSI SHOLAT PINTAR MANDIRI (HYBRID AI WAJAH & IOT SIDIK JARI)
### SMK MUHAMMADIYAH 4 SAMARINDA

---

## 1. INFORMASI PROPOSAL & PENAWARAN

- **Perihal:** Rencana Anggaran Biaya (RAB) & Komparasi Pengadaan Sistem Absensi Sholat Berbasis AI & IoT
- **Instansi Tujuan:** SMK Muhammadiyah 4 Samarinda
- **Lokasi Implementasi:** Masjid / Mushola & Gerbang SMK Muhammadiyah 4 Samarinda
- **Penyedia Solusi:** Tim Pengembang Sistem Tekara / Haris Dev
- **Model Sistem:** *On-Premise / Self-Hosted Hybrid System* (Bebas Biaya Langganan Bulanan/Tahunan)
- **Masa Berlaku Penawaran:** 30 Hari Kalender

---

## 2. LATAR BELAKANG & KEUNGGULAN SOLUSI

Pelaksanaan ibadah sholat berjamaah di lingkungan sekolah membutuhkan pengawasan kehadiran yang akurat, tertib, dan efisien. Sistem absensi konvensional (manual di kertas atau mesin sidik jari komersial standar) memiliki sejumlah kelemahan:
1. **Penumpukan Antrean Jamaah:** Siswa harus menempelkan jari satu per satu sebelum wudhu/masuk masjid sehingga memperlambat jamaah.
2. **Ketergantungan Biaya Langganan (SaaS Vendor):** Mesin pabrikan umumnya mengenakan biaya cloud bulanan/tahunan yang membebani kas sekolah.
3. **Ketidaksesuaian Logika Kerja:** Mesin absen komersial dirancang untuk jam masuk/keluar kantor, bukan untuk fleksibilitas waktu sholat (Subuh, Dzuhur, Ashar) yang memiliki jendela waktu khusus.

**Solusi yang Ditawarkan:**
Sistem Absensi Sholat Pintar dirancang khusus dengan sistem *Dual-Mode*:
- **Mode Utama (AI Face Recognition Multi-Wajah):** Kamera wide-angle resolusi tinggi mendeteksi rombongan jamaah sekaligus dalam satu frame, memverifikasi wajah murid secara instan tanpa perlu antre fisik.
- **Mode Cadangan / Khusus (Terminal IoT Sidik Jari Tekara v2):** Terminal berbasis ESP32 dan optical sensor AS608 berkecepatan tinggi (~90 ms per pembacaan) untuk siswa atau guru yang membutuhkan alternatif atau verifikasi kedua.
- **Integrasi Waktu Sholat Otomatis:** Sistem web Laravel membuka dan menutup sesi absen otomatis sesuai jadwal sholat harian, mencatat log secara real-time, dan menyimpan bukti foto kehadiran.

---

## 3. MATRIKS KOMPARASI PENGADAAN (PROYEK UTAMA VS PEMBANDING)

Sebagai acuan studi kelayakan anggaran, berikut disajikan perbandingan antara opsi rekomendasi dan opsi alternatif di pasaran:

| Parameter Evaluasi | Paket Rekomendasi (Hybrid AI + IoT) | Paket Pembanding 1 (IoT Sidik Jari Standar) | Paket Pembanding 2 (Mesin Vendor + Cloud SaaS) |
| :--- | :--- | :--- | :--- |
| **Teknologi Utama** | AI Wajah (*InsightFace*) + IoT ESP32 AS608 | Terminal Sidik Jari Mandiri + Kartu RFID | Mesin Pabrikan Impor + Layanan Cloud Vendor |
| **Modalitas Presensi** | **Dual-Mode** (Wajah Otomatis & Sidik Jari) | Single-Mode (Sidik Jari / RFID) | Single-Mode (Sidik Jari / Kartu) |
| **Kecepatan di Masjid** | **Sangat Cepat** (scan rombongan sekaligus) | Lambat - Sedang (antre satu per satu) | Lambat - Sedang (antre satu per satu) |
| **Kesesuaian Jadwal Sholat** | **100% Otomatis** mengikuti sesi sholat | Perlu penyesuaian jadwal manual | Kaku (hanya format jam kantor biasa) |
| **Bukti Kehadiran** | Foto potret jamaah otomatis tersimpan | Tidak ada bukti visual | Umumnya tidak ada foto bukti |
| **Biaya Lisensi Rutin** | **Rp 0** (Milik sekolah selamanya) | **Rp 0** (Milik sekolah selamanya) | Rp 3.000.000 - Rp 5.000.000 / tahun |
| **Biaya Investasi Awal** | **Rp 8.950.000** | **Rp 7.450.000** | **Rp 12.800.000** |
| **Total Biaya 3 Tahun (TCO)** | **Rp 8.950.000** | **Rp 7.450.000** | **Rp 21.800.000 - Rp 27.800.000** |
| **Rekomendasi Kelayakan** | **Sangat Direkomendasikan (Terbaik)** | Opsi Alternatif Terbatas | Tidak Efisien Jangka Panjang |

---

## 4. RINCIAN ANGGARAN BIAYA (RAB) PAKET REKOMENDASI

### A. Perangkat Keras & Komponen Lapangan (Hardware)

| No | Uraian Perangkat & Spesifikasi | Vol | Satuan | Harga Satuan (Rp) | Subtotal (Rp) |
| :---: | :--- | :---: | :---: | :---: | :---: |
| A1 | **Terminal IoT Sidik Jari (Tekara v2):**<br>- Microcontroller ESP32 DevKit V1 30-Pin High Clock Rate<br>- Optical Fingerprint Sensor AS608 Fast-Response Module<br>- Display Status I2C OLED Screen 0.96 inch<br>- Enclosure Box Custom Wall-Mount Industri<br>- Power Supply Stabilizer 5V 2A & Konektor Daya | 1 | Unit | 1.150.000 | 1.150.000 |
| A2 | **Kamera AI Pengenal Wajah (Masjid/Mushola):**<br>- HD 1080p Ultra Wide-Angle Sensor Camera<br>- Fitur Low-Light CMOS (stabil di pencahayaan dalam ruangan)<br>- Bracket Mounting Dinding / Tripod Kokoh | 1 | Unit | 850.000 | 850.000 |
| A3 | **Paket Kabel & Infrastruktur Kelistrikan:**<br>- Kabel UTP Cat6 (15 meter) + RJ45 Konektor<br>- Stop Kontak Terminal, Adaptor, Ducting Pelindung Kabel | 1 | Paket | 350.000 | 350.000 |
| **Subtotal Hardware (A)** | | | | | **Rp 2.350.000** |

---

### B. Pengembangan Software, AI Engine, & Firmware (Software Development)

| No | Uraian Lingkup Pengembangan Perangkat Lunak | Vol | Satuan | Harga Satuan (Rp) | Subtotal (Rp) |
| :---: | :--- | :---: | :---: | :---: | :---: |
| B1 | **Sistem Web Manajemen Absensi Sholat (Laravel Backend):**<br>- Modul Manajemen Waktu & Jadwal Sholat Terjadwal<br>- Otomasi Siklus Sesi (Auto Open & Auto Close Sesi Sholat)<br>- Manajemen Data Santri/Siswa, Rombel/Kelas, Petugas Piket, & Admin<br>- Dashboard Live Monitoring Kehadiran Jamaah Real-Time<br>- Sistem Rekapitulasi Presensi, Cetak Laporan PDF/Excel, & Auto-Backup Data | 1 | Sistem | 2.500.000 | 2.500.000 |
| B2 | **Mesin Kecerdasan Buatan (AI Multi-Face Recognition Service):**<br>- Integrasi Layanan Python Engine AI (`buffalo_sc` / InsightFace)<br>- Ekstraksi 512 Vector Face Embedding Berkecepatan Tinggi<br>- Deteksi Rombongan (Multi-Face per frame sekaligus)<br>- Pipeline Kompresi & Penyimpanan Foto Bukti Kehadiran | 1 | Modul | 1.800.000 | 1.800.000 |
| B3 | **Firmware ESP32 C++ & RESTful Communication API:**<br>- Firmware Khusus Optimasi Perekaman & Verifikasi Cepat (~90 ms)<br>- Fleksibilitas REST API Dual Mode (Dukungan Offline Local & Online Server)<br>- Sinkronisasi Template Sidik Jari Aman antara Terminal & Database | 1 | Modul | 850.000 | 850.000 |
| **Subtotal Software & AI (B)** | | | | | **Rp 5.150.000** |

---

### C. Jasa Instalasi, Kalibrasi, Pelatihan, & Garansi (Services & Support)

| No | Uraian Jasa & Layanan Teknis | Vol | Satuan | Harga Satuan (Rp) | Subtotal (Rp) |
| :---: | :--- | :---: | :---: | :---: | :---: |
| C1 | **Instalasi Fisik & Setup Jaringan Lapangan:**<br>- Pemasangan Unit Terminal IoT & Kamera di Area Masjid/Mushola<br>- Penataan Kabel (Wiring & Ducting) Rapi & Aman<br>- Pengujian Koneksi Wi-Fi / Local Area Network (LAN) Sekolah | 1 | Layanan | 450.000 | 450.000 |
| C2 | **Deployment Server, Integrasi Docker, & Kalibrasi AI:**<br>- Pemasangan Database MySQL, Web Server, & AI Service<br>- Kalibrasi Sudut Pandang Kamera & Ambang Batas Akurasi Deteksi | 1 | Layanan | 400.000 | 400.000 |
| C3 | **Pelatihan Petugas (Training of Trainer) & Dokumentasi:**<br>- Bimbingan Teknis Operasional untuk Guru Piket & Pengurus Mushola<br>- Penyusunan Buku Panduan Operasional Sistem & Troubleshooting Mandiri | 1 | Paket | 300.000 | 300.000 |
| C4 | **Dukungan Teknis & Garansi Sistem (3 Bulan):**<br>- Pemeliharaan Sistem, Perbaikan Bug, & Konsultasi Teknis Jarak Jauh | 1 | Paket | 300.000 | 300.000 |
| **Subtotal Jasa & Implementasi (C)** | | | | | **Rp 1.450.000** |

---

## 5. REKAPITULASI TOTAL ANGGARAN BIAYA

| No | Komponen Pekerjaan | Alokasi Anggaran (Rp) |
| :---: | :--- | :---: |
| 1 | Subtotal Perangkat Keras & Infrastruktur Sensor (Hardware) | Rp 2.350.000 |
| 2 | Subtotal Perangkat Lunak, Mesin AI, & Firmware (Software) | Rp 5.150.000 |
| 3 | Subtotal Jasa Pemasangan, Pelatihan, & Garansi (Services) | Rp 1.450.000 |
| **TOTAL KESELURUHAN BIAYA** | | **Rp 8.950.000** |

### Terbilang:
> **"Delapan Juta Sembilan Ratus Lima Puluh Ribu Rupiah"**

---

## 6. DETAIL RINCIAN PAKET PEMBANDING

### Paket Pembanding 1: Solusi IoT Mandiri (Hanya Sidik Jari)
- **Total Investasi:** **Rp 7.450.000**
- **Rincian Alokasi:**
  - Hardware: 2 Unit Terminal IoT Sidik Jari ESP32 & Display = Rp 2.100.000
  - Software: Aplikasi Web Standar Laravel Presensi Manual = Rp 3.500.000
  - Jasa: Instalasi Lapangan, Pelatihan, & Setup = Rp 1.850.000
- **Analisis Komparatif:** Selisih biaya hanya Rp 1.500.000 lebih murah, namun kehilangan kapabilitas AI Wajah sehingga jamaah sholat tetap mengalami kendala antrean lambat saat jam puncak sholat.

### Paket Pembanding 2: Pengadaan Mesin Komersial Vendor Luar + Cloud SaaS
- **Total Investasi Tahun Pertama:** **Rp 12.800.000**
- **Rincian Alokasi:**
  - Hardware: 2 Unit Mesin Absensi Face/Fingerprint Komersial Pabrikan = Rp 6.200.000
  - Lisensi Cloud SaaS Vendor (Tahun Pertama): Rp 3.600.000
  - Jasa Instalasi & Setup Vendor Luar: Rp 3.000.000
  - *Biaya Berulang (Tahun ke-2 dan seterusnya): Rp 3.600.000 - Rp 5.000.000 / tahun*
- **Analisis Komparatif:** Biaya awal 43% lebih mahal dan menimbulkan beban kas rutin tahunan bagi sekolah, serta menu tidak dapat dikustomisasi sesuai kalender dan jadwal sholat sekolah.

---

## 7. SKEMA TAHAPAN PEMBAYARAN (TERMIN)

Pembayaran dilakukan bertahap sesuai progres pencapaian pekerjaan (*milestone*):

| Termin | Persentase | Nominal (Rp) | Syarat Pembayaran / Milestone |
| :---: | :---: | :---: | :--- |
| **Termin I** | 40% | **Rp 3.580.000** | Uang Muka (DP) saat proposal disetujui & penandatanganan SPK untuk pengadaan hardware dan inisiasi modul. |
| **Termin II** | 40% | **Rp 3.580.000** | Dibayarkan setelah seluruh perangkat fisik terpasang di mushola dan uji coba sistem berjalan lancar (*trial run*). |
| **Termin III** | 20% | **Rp 1.790.000** | Pelunasan setelah pelatihan operator/guru selesai, penyerahan buku panduan, dan Berita Acara Serah Terima (BAST). |
| **Total** | **100%** | **Rp 8.950.000** | |

---

## 8. JADWAL PELAKSANAAN PEKERJAAN (TIMELINE)

| No | Tahapan Aktivitas | Minggu 1 | Minggu 2 | Minggu 3 | Minggu 4 |
| :---: | :--- | :---: | :---: | :---: | :---: |
| 1 | Pengadaan Komponen Hardware, Sensor, & Box IoT | [ X ] | [   ] | [   ] | [   ] |
| 2 | Perakitan Unit Terminal Tekara v2 & Flashing Firmware | [ X ] | [ X ] | [   ] | [   ] |
| 3 | Konfigurasi Web Laravel & Integrasi Service AI Python | [   ] | [ X ] | [ X ] | [   ] |
| 4 | Instalasi Fisik & Pemasangan Jaringan di Mushola | [   ] | [   ] | [ X ] | [   ] |
| 5 | Uji Coba Lapangan (Trial Run Sholat Berjamaah) | [   ] | [   ] | [ X ] | [ X ] |
| 6 | Pelatihan Operator Piket, Serah Terima, & Garansi | [   ] | [   ] | [   ] | [ X ] |

---

## 9. LEMBAR PENGESAHAN PENAWARAN

Samarinda, 22 Oktober 2026

| Diajukan Oleh, | Mengetahui & Menyetujui, |
| :---: | :---: |
| **Pengembang Sistem / Penyedia Solusi** | **Kepala SMK Muhammadiyah 4 Samarinda** |
| <br><br><br><br> | <br><br><br><br> |
| **Hari Poppy Latip (Haris)**<br>Lead Developer & IoT Specialist | **..................................................**<br>NIP / NBM. |
