# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Absen Solat SMKIM4
#
# Empat tahap pembangunan:
#   aset    memasang paket npm lalu membangun berkas Vite (public/build)
#   vendor  memasang paket composer sesuai composer.lock
#   app     PHP-FPM 8.5 berisi kode aplikasi
#   web     nginx yang menyajikan berkas statis dan meneruskan PHP ke app
#
# Kode aplikasi menyatu di dalam citra. Hanya folder storage dan basis data
# yang dipasang sebagai volume pada docker-compose.yml, sebab memasang akar
# proyek akan menutupi vendor dan public/build yang sudah dibangun di sini.
#
# DUA HAL YANG JANGAN DIUBAH TANPA ALASAN
#
# 1. PHP di sini 8.5. composer.json menulis "^8.3", tetapi yang menentukan
#    adalah composer.lock: vendor/composer/platform_check.php menuntut
#    PHP >= 8.4.1. Pada proyek lain pernah terjadi wadah queue dan scheduler
#    mati berulang dengan kode 255 karena citra PHP-nya lebih tua, sementara
#    wadah app tetap tertulis sehat sebab pemeriksaan kesehatannya hanya
#    membuka soket FastCGI tanpa benar-benar menjalankan Laravel. Karena itu
#    ada penjaga versi di bawah dan pemeriksaan kesehatan yang menjalankan
#    Laravel.
#
# 2. opcache, pdo_sqlite, dan sqlite3 tidak boleh diminta lewat
#    docker-php-ext-install. Ketiganya sudah menyatu di citra resmi PHP dan
#    permintaannya membuat pembangunan gagal dengan kode keluar 2.
# ---------------------------------------------------------------------------

# ---------------------------------------------------------------------------
# Tahap 1: berkas aset
# ---------------------------------------------------------------------------
FROM node:24-slim AS aset

WORKDIR /app

COPY package.json package-lock.json .npmrc ./

# .npmrc proyek menulis ignore-scripts=true, sama seperti di komputer,
# jadi paket yang dipasang identik. Bila lock dan package.json tidak lagi
# sepadan, npm ci berhenti dengan galat; npm install menjadi jalan mundurnya.
RUN npm ci --no-audit --no-fund || npm install --no-audit --no-fund

COPY . .
RUN npm run build

# ---------------------------------------------------------------------------
# Tahap 2: paket composer
# ---------------------------------------------------------------------------
FROM composer:2 AS composer

FROM php:8.5-cli-alpine AS vendor

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

RUN apk add --no-cache unzip

# Paket composer hanya menuntut ekstensi bawaan citra PHP (dom, fileinfo,
# filter, hash, iconv, json, libxml, openssl, pcre, phar, session, tokenizer,
# xml, xmlwriter). ctype dan mbstring punya polyfill, tetapi bila ekstensinya
# ada dipakai langsung.
RUN set -eux; \
    if ! php -m | grep -qix mbstring; then \
        apk add --no-cache --virtual .bangun-onig $PHPIZE_DEPS oniguruma-dev; \
        docker-php-ext-install -j"$(nproc)" mbstring; \
        apk del .bangun-onig; \
    fi

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction \
        --no-progress

# ---------------------------------------------------------------------------
# Tahap 3: aplikasi (PHP-FPM)
# ---------------------------------------------------------------------------
FROM php:8.5-fpm-alpine AS app

RUN apk update && apk upgrade --no-cache && apk add --no-cache su-exec tzdata

# Yang dipasang hanya pdo_mysql, pcntl, dan bila perlu mbstring serta curl.
# opcache, pdo_sqlite, dan sqlite3 sudah ada di dalam citra.
RUN set -eux; \
    apk add --no-cache --virtual .bangun $PHPIZE_DEPS oniguruma-dev curl-dev; \
    if ! php -m | grep -qix mbstring; then docker-php-ext-install -j"$(nproc)" mbstring; fi; \
    if ! php -m | grep -qix curl; then docker-php-ext-install -j"$(nproc)" curl; fi; \
    docker-php-ext-install -j"$(nproc)" pdo_mysql pcntl; \
    apk del .bangun; \
    apk add --no-cache libcurl oniguruma

# Penjaga: pembangunan berhenti lebih awal bila PHP terlalu tua atau ada
# ekstensi yang hilang, bukan setelah wadah berjalan lalu mati diam-diam.
RUN set -eux; \
    php -r 'if (PHP_VERSION_ID < 80401) { fwrite(STDERR, "PHP ".PHP_VERSION." lebih tua dari 8.4.1 yang dituntut composer.lock.\n"); exit(1); }'; \
    for e in ctype dom fileinfo filter hash iconv json libxml mbstring openssl pcre phar session tokenizer xml xmlwriter zlib; do \
        php -m | grep -qix "$e" || { echo "Ekstensi PHP hilang: $e"; exit 1; }; \
    done; \
    php -m | grep -qix 'Zend OPcache' || { echo "opcache tidak aktif"; exit 1; }; \
    php -r 'exit(in_array("mysql", PDO::getAvailableDrivers(), true) ? 0 : 1);' || { echo "pdo_mysql tidak siap"; exit 1; }; \
    php -r 'exit(function_exists("pcntl_fork") ? 0 : 1);' || { echo "pcntl tidak siap"; exit 1; }; \
    php -r 'exit(function_exists("curl_init") ? 0 : 1);' || { echo "curl tidak siap"; exit 1; }; \
    echo 'Pemeriksaan PHP, ekstensi, dan driver basis data lulus.'

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-absen-solat.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY --from=composer /usr/bin/composer /usr/local/bin/composer

COPY . .
COPY --from=aset /app/public/build ./public/build

# Peta kelas dibangun di sini supaya jalur berkasnya sesuai tata letak akhir.
# Skrip package:discover ikut berjalan pada langkah ini.
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-interaction

RUN set -eux; \
    mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache; \
    chown -R www-data:www-data storage bootstrap/cache

# Pemeriksaan kesehatan harus membuktikan Laravel benar-benar hidup, bukan
# sekadar soket FastCGI terbuka.
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=5 \
    CMD php artisan --version >/dev/null 2>&1 && php -r 'exit(@fsockopen("127.0.0.1", 9000) ? 0 : 1);'

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# Tahap 4: web (nginx)
# ---------------------------------------------------------------------------
FROM nginx:1.29-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=aset /app/public /var/www/html/public

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
