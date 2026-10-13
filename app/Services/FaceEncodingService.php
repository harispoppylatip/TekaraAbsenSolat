<?php

namespace App\Services;

use App\Models\FaceEmbedding;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Jembatan ke layanan wajah Python yang menghitung angka pembanding wajah.
 *
 * Aplikasi ini sengaja tidak menjalankan mesin wajah langsung dari PHP, karena
 * memuat model wajah butuh beberapa detik dan terlalu berat bila dilakukan pada
 * setiap scan. Yang dipakai adalah layanan kecil, sehingga modelnya sudah siap
 * dan hanya perlu dikirimi foto.
 *
 * Alamat layanan diambil dari `FACE_SERVICE_URL` di berkas .env. Bawaannya
 * localhost (satu komputer), dan diisi alamat komputer lain bila mesin wajah
 * dipisah. Mengubah alamat wajib diikuti `php artisan config:clear`.
 */
class FaceEncodingService
{
    /** Foto berhasil dibaca dan wajahnya ditemukan. */
    public const StatusSuccess = 'success';

    /** Foto terbaca, tetapi tidak ada wajah yang bisa dibaca. */
    public const StatusNoFace = 'no_face';

    /** Foto memuat lebih dari satu wajah, sehingga tidak boleh dipakai. */
    public const StatusMultipleFaces = 'multiple_faces';

    /** Wajah terlalu kecil di dalam bingkai foto untuk dipercaya. */
    public const StatusFaceTooSmall = 'face_too_small';

    /** Layanan wajah tidak bisa dihubungi atau tidak merespons. */
    public const StatusUnavailable = 'service_unavailable';

    /** Balasan layanan wajah tidak sesuai yang diharapkan. */
    public const StatusInvalid = 'invalid_response';

    /** Foto terlalu gelap untuk dijadikan pembanding wajah. */
    public const StatusTooDark = 'too_dark';

    /** Foto terlalu terang sampai wajahnya sulit dibaca. */
    public const StatusTooBright = 'too_bright';

    /** Foto kurang tajam, biasanya karena kamera bergerak. */
    public const StatusBlurry = 'blurry';

    /** Wajah menghadap terlalu jauh dari kamera. */
    public const StatusBadPose = 'bad_pose';

    /**
     * Seluruh keadaan mutu foto yang dikenali.
     *
     * Keadaan ini hanya muncul bila pemanggil meminta pemeriksaan mutu, yaitu
     * dari halaman pendaftaran wajah. Alur absen di gerbang tidak memintanya,
     * sehingga ambang mutu bisa disetel tanpa mengubah aturan kehadiran.
     *
     * @var array<int, string>
     */
    public const QualityStatuses = [
        self::StatusTooDark,
        self::StatusTooBright,
        self::StatusBlurry,
        self::StatusBadPose,
    ];

    /**
     * Pesan saat nama model di mesin wajah tidak sama dengan yang diharapkan
     * aplikasi.
     *
     * Kedua nama model ditulis supaya operator langsung tahu mana yang harus
     * disamakan. Tanpa itu pesannya hanya mengatakan ada yang tidak cocok, dan
     * penyebabnya harus ditebak sendiri. Kejadian nyata: berkas .env tidak ikut
     * tersalin saat proyek dipindah ke server (berkas itu memang tidak dilacak
     * git), sehingga aplikasi masih mengharapkan model lama sementara mesin
     * wajahnya sudah memakai model baru.
     *
     * Dua tempat kosong: %1$s = model yang diharapkan aplikasi, %2$s = model
     * yang dilaporkan mesin wajah.
     */
    public const ModelMismatchMessage = 'Versi mesin wajah berbeda. Aplikasi mengharapkan %1$s tetapi mesin melaporkan %2$s. Samakan FACE_MODEL di berkas .env aplikasi dengan FACE_MODEL_NAME di berkas .env mesin wajah.';

    /**
     * Hitung angka pembanding wajah dari sebuah foto yang diunggah lewat
     * halaman.
     *
     * @return array{status: string, encoding: array<int, float>|null, face_count: int, face_size: int, model: string|null, message: string, photo: string|null}
     */
    public function encode(UploadedFile $image, bool $withPhoto = false): array
    {
        $contents = @file_get_contents($image->getRealPath());

        return $this->encodeContents(is_string($contents) ? $contents : '', $withPhoto);
    }

    /**
     * Hitung angka pembanding wajah dari isi sebuah berkas foto.
     *
     * Bila `$withPhoto` bernilai benar, mesin wajah juga diminta mengirim balik
     * foto seluruh bingkai yang sudah diperkecil sebagai bukti absen. Foto itu
     * hanya diambil bila bentuknya wajar dan memang diminta; apa pun yang
     * mencurigakan diabaikan supaya absennya tidak ikut gagal.
     *
     * Foto yang memuat lebih dari satu wajah dan foto yang wajahnya terlalu
     * kecil ditolak di sini, karena keduanya tetap menghasilkan angka yang
     * terlihat wajar padahal tidak layak dipakai memutuskan identitas.
     *
     * @return array{status: string, encoding: array<int, float>|null, face_count: int, face_size: int, model: string|null, message: string, photo: string|null}
     */
    public function encodeContents(string $contents, bool $withPhoto = false): array
    {
        return $this->encodeWith($contents, $withPhoto, false);
    }

    /**
     * Hitung angka pembanding wajah sekaligus periksa mutu fotonya.
     *
     * Dipakai halaman pendaftaran wajah, tempat foto yang gelap, buram, atau
     * menghadap terlalu jauh ke samping HARUS ditolak sebelum angkanya disimpan.
     * Foto seperti itu tetap menghasilkan angka yang terlihat sah, sehingga
     * tanpa pemeriksaan ini datanya masuk dan baru ketahuan bermasalah saat
     * pemiliknya tidak pernah dikenali di depan kamera absen.
     *
     * Pemeriksaan mutu sengaja tidak menjadi bawaan `encodeContents()`, sebab
     * alur absen di gerbang memakai jalur yang sama dan aturan pencatatan
     * kehadiran yang sudah berjalan tidak boleh ikut berubah saat ambang mutu
     * disetel.
     *
     * @return array{status: string, encoding: array<int, float>|null, face_count: int, face_size: int, model: string|null, message: string, photo: string|null}
     */
    public function encodeForEnrollment(string $contents): array
    {
        return $this->encodeWith($contents, false, true);
    }

    /**
     * @return array{status: string, encoding: array<int, float>|null, face_count: int, face_size: int, model: string|null, message: string, photo: string|null}
     */
    private function encodeWith(string $contents, bool $withPhoto, bool $withQuality): array
    {
        if ($contents === '') {
            return $this->failure(self::StatusInvalid, 'Foto tidak terbaca. Ambil ulang fotonya.');
        }

        try {
            $response = Http::timeout((int) config('face.timeout', 90))
                ->acceptJson()
                ->attach('image', $contents, 'scan.jpg')
                ->post($this->serviceUrl().'/encode?'.$this->query($withPhoto, $withQuality));
        } catch (ConnectionException) {
            return $this->failure(self::StatusUnavailable, $this->unavailableMessage());
        }

        if (! $response->successful()) {
            return $this->failure(self::StatusInvalid, 'Layanan wajah menolak foto ini. Ambil ulang fotonya.');
        }

        $data = $response->json();

        if (! is_array($data)) {
            return $this->failure(self::StatusInvalid, 'Balasan layanan wajah tidak dikenali.');
        }

        $status = (string) ($data['status'] ?? '');
        $faceCount = (int) ($data['face_count'] ?? 1);

        if ($status === self::StatusNoFace) {
            return $this->failure(
                self::StatusNoFace,
                'Wajah tidak terlihat. Dekatkan wajah ke kamera dan pastikan cahaya cukup.',
                $faceCount,
            );
        }

        if ($status === self::StatusMultipleFaces) {
            return $this->failure(
                self::StatusMultipleFaces,
                'Ada lebih dari satu wajah di depan kamera. Absen satu orang saja supaya tidak salah catat.',
                $faceCount,
            );
        }

        // Mutu diperiksa lebih dulu daripada angka wajah, sebab saat mutunya
        // ditolak mesin wajah memang tidak mengirim angka apa pun. Tanpa cabang
        // ini, penolakan yang sudah dijelaskan mesin wajah akan muncul sebagai
        // "balasan tidak dikenali" dan pesannya jadi tidak berguna.
        //
        // Cabangnya sengaja hanya berlaku bila pemeriksaan mutu MEMANG diminta.
        // Alur absen di gerbang memakai jalur yang sama, dan aturan pencatatan
        // kehadiran tidak boleh ikut berubah hanya karena ambang mutu disetel
        // untuk pendaftaran. Penolakan mutu yang datang tanpa diminta karena itu
        // diperlakukan sebagai balasan yang tidak dikenali.
        if ($withQuality && in_array($status, self::QualityStatuses, true)) {
            return $this->failure(
                $status,
                is_string($data['message'] ?? null) && $data['message'] !== ''
                    ? $data['message']
                    : 'Foto ini belum bisa dipakai. Ambil ulang fotonya.',
                $faceCount,
            );
        }

        $model = is_string($data['model'] ?? null) ? trim($data['model']) : '';
        $expected = (string) config('face.model', FaceEmbedding::DefaultModel);

        // Angka wajah dari model yang berbeda tidak bisa dibandingkan dengan
        // angka wajah yang sudah tersimpan, jadi mesin yang versinya berbeda
        // ditolak lebih dulu daripada diam-diam mencocokkan yang tidak setara.
        //
        // Kedua nama model DITULIS di pesannya, sebab tanpa itu operator hanya
        // tahu ada yang tidak cocok tetapi tidak tahu harus menyamakan yang mana.
        // Kejadian nyata: berkas .env tidak ikut tersalin saat proyek dipindah ke
        // server (berkas itu memang tidak dilacak git), sehingga aplikasi masih
        // mengharapkan model lama sementara mesin wajahnya sudah memakai model
        // baru, dan pesan tanpa kedua nama itu tidak bisa menunjukkan sebabnya.
        if ($model !== '' && $model !== $expected) {
            Log::warning('Mesin wajah memakai model yang berbeda dari data tersimpan.', [
                'mesin' => $model,
                'diharapkan' => $expected,
            ]);

            return $this->failure(
                self::StatusInvalid,
                sprintf(self::ModelMismatchMessage, $expected, $model),
                $faceCount,
            );
        }

        $encoding = $data['encoding'] ?? null;

        if (! is_array($encoding) || count($encoding) !== FaceEmbedding::Dimensions) {
            return $this->failure(self::StatusInvalid, 'Wajah tidak terbaca dengan baik. Ambil ulang fotonya.', $faceCount);
        }

        $faceSize = (int) ($data['face_size'] ?? 0);
        $minSize = (int) config('face.min_face_size', 80);

        if ($faceSize > 0 && $minSize > 0 && $faceSize < $minSize) {
            return $this->failure(
                self::StatusFaceTooSmall,
                'Wajah terlalu kecil di layar. Dekatkan wajah ke kamera, lalu coba lagi.',
                $faceCount,
            );
        }

        return [
            'status' => self::StatusSuccess,
            'encoding' => FaceEmbedding::normalizeEmbedding($encoding),
            'face_count' => $faceCount,
            'face_size' => $faceSize,
            'model' => $model === '' ? null : $model,
            'message' => 'Wajah terbaca.',
            'photo' => $withPhoto ? $this->proofPhoto($data['photo'] ?? null) : null,
        ];
    }

    /**
     * Hitung angka pembanding SELURUH wajah yang terlihat di satu foto.
     *
     * Dipakai kamera absen yang bisa memuat beberapa orang sekaligus. Setiap
     * wajah dihitung dan dilaporkan sendiri sendiri beserta kotaknya, sehingga
     * aplikasi bisa mencatat kehadiran tiap orang tanpa menunggu yang lain
     * keluar dari bingkai.
     *
     * Satu wajah yang angka wajahnya gagal dihitung tidak menggagalkan wajah
     * lain: entri itu tetap dilaporkan dengan `encoding` bernilai null supaya
     * halaman tetap bisa menggambar kotaknya dan menandainya belum dikenal.
     *
     * @return array{status: string, message: string, width: int, height: int, photo: string|null, faces: array<int, array{index: int, top: int, right: int, bottom: int, left: int, face_size: int, encoding: array<int, float>|null, error: string|null}>}
     */
    public function detectContents(string $contents, bool $withPhoto = false): array
    {
        $empty = ['status' => self::StatusInvalid, 'message' => '', 'width' => 0, 'height' => 0, 'photo' => null, 'faces' => []];

        if ($contents === '') {
            return array_merge($empty, ['message' => 'Foto tidak terbaca. Ambil ulang fotonya.']);
        }

        try {
            $response = Http::timeout((int) config('face.timeout', 90))
                ->acceptJson()
                ->attach('image', $contents, 'scan.jpg')
                ->post($this->serviceUrl().'/detect?'.$this->query($withPhoto));
        } catch (ConnectionException) {
            return array_merge($empty, ['status' => self::StatusUnavailable, 'message' => $this->unavailableMessage()]);
        }

        if (! $response->successful()) {
            return array_merge($empty, ['message' => 'Layanan wajah menolak foto ini. Ambil ulang fotonya.']);
        }

        $data = $response->json();

        if (! is_array($data)) {
            return array_merge($empty, ['message' => 'Balasan layanan wajah tidak dikenali.']);
        }

        $status = (string) ($data['status'] ?? '');

        if ($status === 'error') {
            return array_merge($empty, ['message' => 'Layanan wajah menolak foto ini. Ambil ulang fotonya.']);
        }

        if ($status === self::StatusNoFace || $status === 'no_face') {
            return array_merge($empty, ['status' => self::StatusNoFace, 'message' => 'Wajah tidak terlihat.']);
        }

        $model = is_string($data['model'] ?? null) ? trim($data['model']) : '';
        $expected = (string) config('face.model', FaceEmbedding::DefaultModel);

        // Angka wajah dari model yang berbeda tidak bisa dibandingkan dengan
        // angka wajah yang sudah tersimpan.
        if ($model !== '' && $model !== $expected) {
            Log::warning('Mesin wajah memakai model yang berbeda dari data tersimpan.', [
                'mesin' => $model,
                'diharapkan' => $expected,
            ]);

            return array_merge($empty, ['message' => sprintf(self::ModelMismatchMessage, $expected, $model)]);
        }

        $faces = [];

        foreach ((array) ($data['faces'] ?? []) as $face) {
            if (! is_array($face)) {
                continue;
            }

            $box = is_array($face['bbox'] ?? null) ? $face['bbox'] : [];

            if ($box === []) {
                continue;
            }

            $encoding = $face['encoding'] ?? null;
            $valid = is_array($encoding) && count($encoding) === FaceEmbedding::Dimensions;

            $faces[] = [
                'index' => count($faces),
                'top' => (int) ($box['top'] ?? 0),
                'right' => (int) ($box['right'] ?? 0),
                'bottom' => (int) ($box['bottom'] ?? 0),
                'left' => (int) ($box['left'] ?? 0),
                'face_size' => (int) ($face['face_size'] ?? 0),
                'encoding' => $valid ? FaceEmbedding::normalizeEmbedding($encoding) : null,
                'error' => $valid ? null : (is_string($face['error'] ?? null) ? $face['error'] : 'encoding_failed'),
            ];
        }

        if ($faces === []) {
            return array_merge($empty, ['status' => self::StatusNoFace, 'message' => 'Wajah tidak terlihat.']);
        }

        return [
            'status' => self::StatusSuccess,
            'message' => count($faces) === 1 ? 'Wajah terbaca.' : count($faces).' wajah terbaca.',
            'width' => (int) ($data['width'] ?? 0),
            'height' => (int) ($data['height'] ?? 0),
            'photo' => $withPhoto ? $this->proofPhoto($data['photo'] ?? null) : null,
            'faces' => $faces,
        ];
    }

    /**
     * Layanan wajah hidup atau tidak. Dipakai halaman wajah untuk memberi tahu
     * operator kalau mesin wajahnya belum dinyalakan.
     */
    public function isAvailable(): bool
    {
        return $this->health() !== null;
    }

    /**
     * Keadaan layanan wajah menurut pemeriksaan kesehatannya.
     *
     * Dipakai halaman wajah untuk MENUNJUKKAN sampai di mana kesiapannya,
     * termasuk nama model yang sedang dilaporkan mesin. Halaman itu penting
     * untuk membandingkannya dengan model yang diharapkan aplikasi, sebab dua
     * berkas .env terpisah mengaturnya dan berkas .env TIDAK ikut tersalin saat
     * proyek dipindah ke server. Tanpa perbandingan yang terlihat, keadaan itu
     * hanya muncul sebagai kegagalan unggah yang membingungkan.
     *
     * Mengembalikan null bila layanan tidak bisa dihubungi, jadi pemanggilnya
     * tidak perlu membedakan "mati" dari "membalas tetapi bukan JSON".
     *
     * @return array{model: string, ready: bool, dimensions: int}|null
     */
    public function health(): ?array
    {
        try {
            $response = Http::timeout(3)->acceptJson()->get($this->serviceUrl().'/health');
        } catch (ConnectionException) {
            return null;
        }

        $data = $response->json();

        // Layanan membalas 503 selama modelnya belum selesai dimuat, dan itu
        // tetap berita yang berguna: nama modelnya sudah bisa dibaca.
        if (! is_array($data) || ! $response->successful()) {
            return null;
        }

        return [
            'model' => is_string($data['model'] ?? null) ? trim($data['model']) : '',
            'ready' => ($data['model_ready'] ?? false) === true,
            'dimensions' => (int) ($data['dimensions'] ?? 0),
        ];
    }

    /**
     * Nama model yang diharapkan aplikasi, dipakai halaman untuk membandingkan
     * dengan nama model yang dilaporkan mesin wajah.
     */
    public function expectedModel(): string
    {
        return (string) config('face.model', FaceEmbedding::DefaultModel);
    }

    public function unavailableMessage(): string
    {
        return 'Layanan wajah di server belum aktif. Minta operator menyalakan layanan wajah, lalu coba lagi.';
    }

    private function serviceUrl(): string
    {
        return rtrim((string) config('face.service_url', 'http://127.0.0.1:5005'), '/');
    }

    /**
     * Rumusan alamat pengambilan angka wajah.
     *
     * `require_single=1` meminta mesin wajah menolak sendiri foto yang memuat
     * lebih dari satu orang, bukan diam-diam memakai wajah terbesar. Mesin wajah
     * versi lama mengabaikan permintaan ini, dan dalam hal itu pemeriksaan
     * jumlah wajah tetap dikerjakan di sisi aplikasi.
     *
     * `quality=1` meminta pemeriksaan mutu foto. Hanya halaman pendaftaran yang
     * memintanya.
     */
    private function query(bool $withPhoto, bool $withQuality = false): string
    {
        $query = ['require_single' => 1];

        if ($withPhoto) {
            $query['with_photo'] = 1;
        }

        if ($withQuality) {
            $query['quality'] = 1;
        }

        return http_build_query($query);
    }

    /**
     * Ambil foto bukti absen dari balasan mesin wajah.
     *
     * Balasannya berupa JPEG yang dikodekan base64. Yang diperiksa hanya tiga
     * hal yang murah: bentuk teksnya, batas ukurannya, dan penanda awal berkas
     * JPEG. Semua yang tidak lolos dianggap tidak ada foto, karena absen lebih
     * penting daripada buktinya.
     */
    private function proofPhoto(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $binary = base64_decode($value, true);

        if ($binary === false || $binary === '') {
            return null;
        }

        if (strlen($binary) > max(0, (int) config('solat.proof_max_bytes', 262144))) {
            return null;
        }

        if (! str_starts_with($binary, "\xFF\xD8\xFF")) {
            return null;
        }

        return $binary;
    }

    /**
     * @return array{status: string, encoding: null, face_count: int, face_size: int, model: null, message: string, photo: null}
     */
    private function failure(string $status, string $message, int $faceCount = 0): array
    {
        return [
            'status' => $status,
            'encoding' => null,
            'face_count' => $faceCount,
            'face_size' => 0,
            'model' => null,
            'message' => $message,
            'photo' => null,
        ];
    }
}
