<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Layanan cadangan (backup) dan pemulihan (restore) lengkap sistem dalam satu berkas .zip.
 *
 * Mencakup seluruh tabel basis data operasional dan seluruh folder foto privat
 * (face-photos, solat-proofs, face-photo-queue). Menggunakan pembungkus dan pembaca
 * format standar PKZIP murni berbasis zlib (gzdeflate/gzinflate + crc32) sehingga
 * 100% kompatibel dengan Windows Explorer, WinRAR, dan 7-Zip tanpa bergantung pada
 * ekstensi ext-zip PHP.
 */
class SystemBackupService
{
    public const AppIdentifier = 'absen_solat';

    public const FormatVersion = 1;

    /**
     * Urutan tabel dari induk ke anak agar aman terhadap foreign key saat pemulihan.
     *
     * @var list<string>
     */
    public const Tables = [
        'users',
        'prayer_times',
        'devices',
        'cameras',
        'face_embeddings',
        'face_photo_uploads',
        'fingerprints',
        'solat_sessions',
        'solat_attendance_logs',
    ];

    /**
     * Folder penyimpanan privat pada disk 'local' yang ikut dicadangkan.
     *
     * @var list<string>
     */
    public const StorageFolders = [
        'face-photos',
        SolatProofService::Folder,
        FacePhotoQueueService::Folder,
    ];

    /**
     * Hitung ringkasan jumlah berkas foto dan total ukuran penyimpanan saat ini.
     *
     * @return array{file_count: int, total_bytes: int}
     */
    public function storageSummary(): array
    {
        $disk = Storage::disk('local');
        $fileCount = 0;
        $totalBytes = 0;

        foreach (self::StorageFolders as $folder) {
            if (! $disk->exists($folder)) {
                continue;
            }

            foreach ($disk->allFiles($folder) as $filePath) {
                $fileCount++;
                $totalBytes += (int) $disk->size($filePath);
            }
        }

        return [
            'file_count' => $fileCount,
            'total_bytes' => $totalBytes,
        ];
    }

    /**
     * Bangun arsip .zip berisi basis data lengkap dan seluruh berkas foto privat.
     */
    public function createBackupZipBinary(): string
    {
        $database = [];
        $tableCounts = [];

        foreach (self::Tables as $table) {
            $rows = DB::table($table)
                ->get()
                ->map(static fn (object $row): array => (array) $row)
                ->all();

            $database[$table] = $rows;
            $tableCounts[$table] = count($rows);
        }

        $disk = Storage::disk('local');
        $entries = [];
        $photoCount = 0;

        foreach (self::StorageFolders as $folder) {
            if (! $disk->exists($folder)) {
                continue;
            }

            foreach ($disk->allFiles($folder) as $relativePath) {
                $normalized = str_replace('\\', '/', $relativePath);
                $contents = $disk->get($relativePath);
                if ($contents === null) {
                    continue;
                }

                $entries['storage/'.$normalized] = $contents;
                $photoCount++;
            }
        }

        $manifest = [
            'app' => self::AppIdentifier,
            'version' => self::FormatVersion,
            'created_at' => now()->toIso8601String(),
            'stats' => [
                'tables' => $tableCounts,
                'photos' => $photoCount,
            ],
        ];

        $orderedEntries = [
            'manifest.json' => json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'database.json' => json_encode($database, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'BACA-SAYA.txt' => $this->buildReadmeText($manifest),
        ] + $entries;

        return $this->buildZip($orderedEntries);
    }

    /**
     * Pulihkan basis data dan seluruh berkas foto dari berkas .zip cadangan.
     *
     * @return array{tables: array<string, int>, photos: int, admin_user: User}
     */
    public function restoreFromZipBinary(string $zipBytes, User $currentAdmin): array
    {
        $entries = $this->extractZip($zipBytes);

        if (! isset($entries['manifest.json'], $entries['database.json'])) {
            throw new RuntimeException('Berkas .zip ini bukan berkas cadangan sistem Absen Solat yang sah (manifest.json atau database.json tidak ditemukan).');
        }

        $manifest = json_decode($entries['manifest.json'], true);
        if (! is_array($manifest) || ($manifest['app'] ?? null) !== self::AppIdentifier) {
            throw new RuntimeException('Berkas .zip ini tidak berasal dari aplikasi Absen Solat.');
        }

        $database = json_decode($entries['database.json'], true);
        if (! is_array($database)) {
            throw new RuntimeException('Isi database.json di dalam berkas cadangan rusak atau tidak dapat dibaca.');
        }

        foreach (self::Tables as $table) {
            if ($table === 'fingerprints' && ! array_key_exists($table, $database)) {
                $database[$table] = [];

                continue;
            }

            if (! array_key_exists($table, $database) || ! is_array($database[$table])) {
                throw new RuntimeException("Data tabel '{$table}' tidak ditemukan di dalam berkas cadangan.");
            }
        }

        $adminFallbackAttributes = $currentAdmin->getAttributes();
        $restoredCounts = [];

        Schema::disableForeignKeyConstraints();

        try {
            DB::transaction(function () use ($database, $adminFallbackAttributes, &$restoredCounts): void {
                foreach (array_reverse(self::Tables) as $table) {
                    DB::table($table)->delete();
                }

                foreach (self::Tables as $table) {
                    $rows = $database[$table];
                    $restoredCounts[$table] = count($rows);

                    if ($rows === []) {
                        continue;
                    }

                    $columns = array_flip(Schema::getColumnListing($table));

                    $filteredRows = array_map(
                        static fn (array $row): array => array_intersect_key($row, $columns),
                        $rows,
                    );

                    foreach (array_chunk($filteredRows, 200) as $chunk) {
                        DB::table($table)->insert($chunk);
                    }
                }

                // Pastikan minimal selalu ada 1 akun Administrator agar tidak terkunci keluar.
                $hasAdmin = DB::table('users')->where('role', User::RoleAdmin)->exists();
                if (! $hasAdmin) {
                    $userColumns = array_flip(Schema::getColumnListing('users'));
                    $adminRow = array_intersect_key($adminFallbackAttributes, $userColumns);
                    unset($adminRow['id']);
                    $adminRow['role'] = User::RoleAdmin;
                    DB::table('users')->insert($adminRow);
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        // Pulihkan seluruh folder foto di penyimpanan privat.
        $disk = Storage::disk('local');
        foreach (self::StorageFolders as $folder) {
            $disk->deleteDirectory($folder);
        }

        $restoredPhotos = 0;
        foreach ($entries as $entryName => $content) {
            $normalized = str_replace('\\', '/', $entryName);
            if (! str_starts_with($normalized, 'storage/')) {
                continue;
            }

            $relativePath = substr($normalized, strlen('storage/'));
            if ($relativePath === '' || str_contains($relativePath, '..') || str_ends_with($relativePath, '/')) {
                continue;
            }

            $topFolder = explode('/', $relativePath, 2)[0];
            if (! in_array($topFolder, self::StorageFolders, true)) {
                continue;
            }

            $disk->put($relativePath, $content);
            $restoredPhotos++;
        }

        $adminUser = User::query()
            ->where('role', User::RoleAdmin)
            ->where(function ($query) use ($adminFallbackAttributes): void {
                $query->where('id', $adminFallbackAttributes['id'] ?? 0)
                    ->orWhere('identifier_number', $adminFallbackAttributes['identifier_number'] ?? '');
            })
            ->first()
            ?? User::query()->where('role', User::RoleAdmin)->firstOrFail();

        return [
            'tables' => $restoredCounts,
            'photos' => $restoredPhotos,
            'admin_user' => $adminUser,
        ];
    }

    /**
     * Buat arsip PKZIP standar dari pasangan [path => binaryContent].
     *
     * @param  array<string, string>  $entries
     */
    public function buildZip(array $entries): string
    {
        $localParts = [];
        $centralParts = [];
        $offset = 0;
        $count = 0;
        $dosTime = $this->unixToDosTime(time());

        foreach ($entries as $name => $data) {
            $cleanName = ltrim(str_replace('\\', '/', $name), '/');
            $nameLength = strlen($cleanName);
            $uncompressedSize = strlen($data);
            $crc = crc32($data);
            $compressed = gzdeflate($data, 6);
            $compressedSize = strlen($compressed);

            // Local File Header (0x04034b50)
            $localHeader = pack(
                'VvvvvvVVVvv',
                0x04034B50,
                20,              // Version needed to extract (2.0)
                0x0800,          // General purpose bit flag (UTF-8 filename)
                8,               // Compression method: Deflate (8)
                $dosTime & 0xFFFF,
                ($dosTime >> 16) & 0xFFFF,
                $crc,
                $compressedSize,
                $uncompressedSize,
                $nameLength,
                0                // Extra field length
            ).$cleanName;

            $localChunk = $localHeader.$compressed;
            $localParts[] = $localChunk;

            // Central Directory File Header (0x02014b50)
            $centralParts[] = pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014B50,
                20,              // Version made by
                20,              // Version needed to extract
                0x0800,          // General purpose bit flag (UTF-8)
                8,               // Compression method: Deflate (8)
                $dosTime & 0xFFFF,
                ($dosTime >> 16) & 0xFFFF,
                $crc,
                $compressedSize,
                $uncompressedSize,
                $nameLength,
                0,               // Extra field length
                0,               // File comment length
                0,               // Disk number start
                0,               // Internal file attributes
                32,              // External file attributes (archive)
                $offset          // Relative offset of local header
            ).$cleanName;

            $offset += strlen($localChunk);
            $count++;
        }

        $centralDirectory = implode('', $centralParts);
        $centralSize = strlen($centralDirectory);

        // End of Central Directory Record (0x06054b50)
        $eocd = pack(
            'VvvvvVVv',
            0x06054B50,
            0,
            0,
            $count,
            $count,
            $centralSize,
            $offset,
            0
        );

        return implode('', $localParts).$centralDirectory.$eocd;
    }

    /**
     * Ekstrak berkas PKZIP standar menjadi array [path => binaryContent].
     *
     * @return array<string, string>
     */
    public function extractZip(string $zipBytes): array
    {
        $length = strlen($zipBytes);
        if ($length < 22 || substr($zipBytes, 0, 4) !== "PK\x03\x04") {
            throw new RuntimeException('Berkas yang diunggah bukan berkas arsip .zip yang sah.');
        }

        // Cari End of Central Directory (0x06054b50) dari bagian akhir berkas.
        $maxCommentSearch = min($length, 65557);
        $eocdPos = strrpos(substr($zipBytes, $length - $maxCommentSearch), "PK\x05\x06");
        if ($eocdPos === false) {
            throw new RuntimeException('Struktur arsip .zip tidak lengkap atau rusak.');
        }

        $eocdOffset = ($length - $maxCommentSearch) + $eocdPos;
        $eocd = unpack('vdisk/vstartDisk/vdiskEntries/vtotalEntries/VcentralSize/VcentralOffset/vcommentLen', substr($zipBytes, $eocdOffset + 4, 18));

        if (! is_array($eocd)) {
            throw new RuntimeException('Gagal membaca katalog arsip .zip.');
        }

        $entries = [];
        $cursor = (int) $eocd['centralOffset'];
        $totalEntries = (int) $eocd['totalEntries'];

        for ($i = 0; $i < $totalEntries; $i++) {
            if ($cursor + 46 > $length || substr($zipBytes, $cursor, 4) !== "PK\x01\x02") {
                throw new RuntimeException('Katalog berkas di dalam arsip .zip rusak.');
            }

            $header = unpack(
                'vverMade/vverNeed/vflags/vmethod/vmtime/vmdate/Vcrc/VcompSize/VuncompSize/vnameLen/vextraLen/vcommentLen/vdiskStart/vintAttr/VextAttr/VlocalOffset',
                substr($zipBytes, $cursor + 4, 42)
            );

            $nameLen = (int) $header['nameLen'];
            $extraLen = (int) $header['extraLen'];
            $commentLen = (int) $header['commentLen'];
            $entryName = substr($zipBytes, $cursor + 46, $nameLen);
            $cursor += 46 + $nameLen + $extraLen + $commentLen;

            $localOffset = (int) $header['localOffset'];
            if ($localOffset + 30 > $length || substr($zipBytes, $localOffset, 4) !== "PK\x03\x04") {
                throw new RuntimeException("Header berkas '{$entryName}' di dalam .zip tidak valid.");
            }

            $localHeader = unpack('vverNeed/vflags/vmethod/vmtime/vmdate/Vcrc/VcompSize/VuncompSize/vnameLen/vextraLen', substr($zipBytes, $localOffset + 4, 26));
            $dataOffset = $localOffset + 30 + (int) $localHeader['nameLen'] + (int) $localHeader['extraLen'];
            $compSize = (int) $header['compSize'];
            $method = (int) $header['method'];

            $rawData = substr($zipBytes, $dataOffset, $compSize);

            if ($method === 0) {
                $content = $rawData;
            } elseif ($method === 8) {
                $inflated = @gzinflate($rawData);
                if ($inflated === false) {
                    throw new RuntimeException("Gagal mengekstrak isi berkas '{$entryName}' dari arsip .zip.");
                }
                $content = $inflated;
            } else {
                throw new RuntimeException("Metode kompresi berkas '{$entryName}' tidak didukung.");
            }

            $entries[$entryName] = $content;
        }

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function buildReadmeText(array $manifest): string
    {
        $tables = $manifest['stats']['tables'] ?? [];
        $photos = $manifest['stats']['photos'] ?? 0;

        return implode("\r\n", [
            '===================================================================',
            'ARSIP CADANGAN LENGKAP (FULL BACKUP) - SISTEM ABSEN SOLAT SMK IM 4',
            '===================================================================',
            'Waktu Pembuatan : '.($manifest['created_at'] ?? '-'),
            'Jumlah Pengguna : '.($tables['users'] ?? 0).' akun',
            'Jumlah Sesi     : '.($tables['solat_sessions'] ?? 0).' sesi',
            'Riwayat Absen   : '.($tables['solat_attendance_logs'] ?? 0).' catatan',
            'Sidik Wajah     : '.($tables['face_embeddings'] ?? 0).' data wajah',
            'Total Berkas    : '.$photos.' foto (wajah siswa & bukti absen)',
            '',
            'CARA MEMULIHKAN (RESTORE) DATA:',
            '1. Masuk ke website Absen Solat menggunakan akun Administrator.',
            '2. Buka menu "Pengaturan" di sebelah kiri.',
            '3. Pada kotak "Cadangan & Pemulihan Sistem (Backup & Restore)",',
            '   pilih berkas .zip ini lalu tekan tombol "Pulihkan Data Sekarang".',
            '4. Seluruh data akun, jadwal, riwayat absen, dan foto anak-anak',
            '   akan otomatis kembali seperti semula.',
            '===================================================================',
        ]);
    }

    private function unixToDosTime(int $timestamp): int
    {
        $date = getdate($timestamp);
        if ($date['year'] < 1980) {
            return (1 << 21) | (1 << 16);
        }

        return (($date['year'] - 1980) << 25)
            | ($date['mon'] << 21)
            | ($date['mday'] << 16)
            | ($date['hours'] << 11)
            | ($date['minutes'] << 5)
            | ($date['seconds'] >> 1);
    }
}
