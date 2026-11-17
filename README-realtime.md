# Panduan Mode Real-time: HTTP Polling (Biasa) vs WebSocket (Laravel Reverb)

Aplikasi **Absen Solat SMKIM4** mendukung **2 mode pembaruan data real-time** untuk halaman `/layar`, `/kamera`, dan `/login`:

1. **Mode Biasa (`polling`) — Default**
   - Browser meminta pembaruan data secara berkala (tiap 2,5–4 detik).
   - **Kelebihan:** Hemat RAM, tidak perlu menjalankan container tambahan (`reverb`), dan paling tahan banting di jaringan sekolah.
2. **Mode WebSocket (`websocket`) — Laravel Reverb**
   - Server mendorong sinyal pembaruan langsung ke browser seketika (< 0,2 detik) saat ada absensi baru tercatat (dari kamera wajah, CCTV worker, maupun sidik jari ESP32).
   - **Kelebihan:** Tampilan log & layar monitor langsung terbarui instan dan mengurangi request HTTP polling berulang saat banyak browser terbuka bersamaan.
   - **Pengaman Otomatis:** Jika suatu saat container `reverb` mati atau koneksi WebSocket terputus, browser otomatis kembali memakai polling berkala dan pencatatan absen tetap berjalan normal.

> **Catatan:** Streaming video CCTV (`go2rtc` di `/go2rtc/ws`) tetap menggunakan WebSocket-nya sendiri di kedua mode karena jauh lebih ringan untuk video.

---

## Cara Mengganti Mode (Tanpa Coding Lagi)

### Langkah 1: Ubah Sakelar di Web (Admin)
1. Login sebagai **Admin**, lalu buka menu **Pengaturan** (`/pengaturan`).
2. Cari kartu **Mode Real-time Data (Polling vs WebSocket)**.
3. Pilih mode yang diinginkan:
   - **Mode Biasa (HTTP Polling — Default)**
   - **Mode WebSocket (Laravel Reverb — Instan)**
4. Klik **Simpan Mode Real-time**.

*(Alternatif lewat `.env` jika database `settings` belum pernah diset: ubah `REALTIME_MODE=polling` atau `REALTIME_MODE=websocket`.)*

---

### Langkah 2: Nyalakan atau Matikan Container `reverb` (Khusus Docker)

#### A. Jika Memilih Mode **WebSocket (`websocket`)**
Jalankan container `reverb` di folder project server:
```bash
docker compose --profile reverb up -d reverb
```
Cek apakah container berjalan:
```bash
docker compose ps reverb
```
*(Opsional)* Pastikan `.env` di server memiliki key acak yang kuat untuk produksi:
```env
REALTIME_MODE=websocket
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=absen-solat
REVERB_APP_KEY=ganti-dengan-key-acak
REVERB_APP_SECRET=ganti-dengan-secret-acak
```
Jika mengubah `.env`, jalankan:
```bash
docker compose --profile reverb up -d --force-recreate reverb app queue cctv
docker compose exec -T app php artisan config:clear
```

#### B. Jika Kembali ke Mode **Biasa (`polling`)**
1. Pilih **Mode Biasa (HTTP Polling — Default)** di `/pengaturan` dan simpan.
2. Matikan container `reverb` agar RAM server kembali bebas:
```bash
docker compose stop reverb
docker compose rm -f reverb
```

---

## Ringkasan Cepat

| Kebutuhan | Pengaturan di `/pengaturan` | Status Container `reverb` |
| :--- | :--- | :--- |
| **Mode Biasa (Default / Hemat RAM)** | `Mode Biasa (HTTP Polling)` | Mati (`stop reverb`) |
| **Mode WebSocket (Update Instan)** | `Mode WebSocket (Laravel Reverb)` | Menyala (`--profile reverb up -d reverb`) |
