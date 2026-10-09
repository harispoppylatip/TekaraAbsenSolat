#!/bin/sh
# ---------------------------------------------------------------------------
# Persiapan wadah Absen Solat SMKIM4
#
# Dijalankan setiap wadah hidup: app, queue, dan scheduler memakai skrip ini.
# Tugasnya tiga: memastikan folder yang harus bisa ditulis sudah benar,
# menunggu basis data bisa dihubungi, lalu menjalankan perintah wadah.
# Migrasi dan cache konfigurasi hanya dikerjakan wadah app.
#
# Catatan pengalaman: kegagalan yang tidak bersuara jauh lebih mahal daripada
# wadah yang berhenti. Semua pemeriksaan di bawah mencetak alasannya ke
# stderr supaya jawabannya ada di "docker compose logs".
# ---------------------------------------------------------------------------
set -e

cd /var/www/html

# ---------------------------------------------------------------------------
# Folder yang harus bisa ditulis aplikasi
# ---------------------------------------------------------------------------
mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

# chown bisa gagal pada folder yang dipasang dari Windows atau pada sistem
# berkas yang tidak mendukung pemilik. Kegagalan itu tidak boleh mematikan
# wadah, tetapi harus ketahuan: karena itu hasilnya diperiksa, bukan diabaikan.
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true

for f in storage/framework/views storage/framework/sessions storage/logs bootstrap/cache; do
    if ! su-exec www-data:www-data test -w "$f"; then
        echo "[persiapan] PERINGATAN: $f tidak bisa ditulis oleh www-data." >&2
        echo "[persiapan] Periksa pemasangan volume storage pada docker-compose.yml." >&2
    fi
done

echo "[persiapan] basis data -> host=${DB_HOST:-?} port=${DB_PORT:-?} nama=${DB_DATABASE:-?}"

# ---------------------------------------------------------------------------
# Peran wadah
#
# Citra yang sama dipakai oleh app, queue, dan scheduler. Hanya wadah app
# yang menjalankan migrasi dan cache konfigurasi, sebab kalau ketiganya
# mengerjakan hal yang sama pada saat yang bersamaan, tabel basis data dan
# berkas cache bisa saling menabrak.
# ---------------------------------------------------------------------------
peran='pendamping'
if [ "$1" = 'php-fpm' ]; then
    peran='utama'
fi

echo "[persiapan] peran wadah: $peran, perintah: $*"

# ---------------------------------------------------------------------------
# Menunggu basis data bisa dihubungi (semua peran)
# ---------------------------------------------------------------------------
echo '[persiapan] menunggu basis data bisa dihubungi'

siap='false'
i=0
while [ "$i" -lt 30 ]; do
    if su-exec www-data:www-data php artisan db:show --json >/dev/null 2>&1; then
        siap='true'
        break
    fi
    i=$((i + 1))
    sleep 2
done

if [ "$siap" != 'true' ]; then
    echo '[persiapan] GAGAL menghubungi basis data setelah 60 detik.' >&2
    echo '[persiapan] Periksa DB_HOST_DOCKER, DB_USERNAME_DOCKER, dan' >&2
    echo '[persiapan] DB_PASSWORD_DOCKER di berkas .env, lalu jalankan:' >&2
    echo '[persiapan]   docker compose exec app php artisan db:show' >&2
fi

# ---------------------------------------------------------------------------
# Migrasi basis data (hanya wadah app, hanya bila diminta)
# ---------------------------------------------------------------------------
if [ "${RUN_MIGRATIONS:-false}" = "true" ] && [ "$peran" = 'utama' ] && [ "$siap" = 'true' ]; then
    echo '[persiapan] menjalankan migrasi'
    if su-exec www-data:www-data php artisan migrate --force; then
        echo '[persiapan] migrasi selesai'
    else
        echo '[persiapan] GAGAL menjalankan migrasi. Aplikasi tetap dijalankan' >&2
        echo '[persiapan] supaya bisa diperiksa lewat halaman web, lalu jalankan' >&2
        echo '[persiapan] migrasinya sendiri dengan perintah:' >&2
        echo '[persiapan]   docker compose exec app php artisan migrate --force' >&2
    fi
fi

# ---------------------------------------------------------------------------
# Cache konfigurasi, rute, dan tampilan (hanya wadah app, hanya bila diminta)
# ---------------------------------------------------------------------------
if [ "${RUN_OPTIMIZE:-false}" = "true" ] && [ "$peran" = 'utama' ]; then
    echo '[persiapan] membangun cache konfigurasi, rute, dan tampilan'
    su-exec www-data:www-data php artisan optimize || true
fi

# ---------------------------------------------------------------------------
# Jalankan perintah wadah
# ---------------------------------------------------------------------------
case "$1" in
    php-fpm)
        # Proses induk PHP-FPM tetap berjalan sebagai root seperti citra resmi
        # PHP, lalu pekerjanya diturunkan menjadi www-data sesuai
        # /usr/local/etc/php-fpm.d/www.conf.
        exec "$@"
        ;;
    *)
        # Antrean, penjadwal, dan perintah artisan dijalankan sebagai www-data
        # supaya berkas yang mereka tulis bisa dibaca dan dihapus pekerja FPM.
        exec su-exec www-data:www-data "$@"
        ;;
esac
