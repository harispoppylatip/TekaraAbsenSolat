<?php

namespace App\Services;

use App\Models\FaceEmbedding;
use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
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

    /** Pemalsuan foto terdeteksi (layar HP/monitor atau foto kertas). */
    public const StatusSpoofDetected = 'spoof_detected';

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
            $response = Http::connectTimeout(3)
                ->timeout((int) config('face.timeout', 45))
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

        if (Setting::antiSpoofingEnabled() && ($status === self::StatusSpoofDetected || $status === 'spoof_detected')) {
            return $this->failure(
                self::StatusSpoofDetected,
                is_string($data['message'] ?? null) && $data['message'] !== ''
                    ? $data['message']
                    : 'Pemalsuan foto terdeteksi: Wajib menggunakan wajah asli secara langsung.',
                $faceCount,
                (int) ($data['face_size'] ?? 0),
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
            $response = Http::connectTimeout(3)
                ->timeout((int) config('face.detect_timeout', 15))
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
            $antiSpoofEnabled = Setting::antiSpoofingEnabled();

            $faces[] = [
                'index' => count($faces),
                'top' => (int) ($box['top'] ?? 0),
                'right' => (int) ($box['right'] ?? 0),
                'bottom' => (int) ($box['bottom'] ?? 0),
                'left' => (int) ($box['left'] ?? 0),
                'face_size' => (int) ($face['face_size'] ?? 0),
                'encoding' => $valid ? FaceEmbedding::normalizeEmbedding($encoding) : null,
                'error' => $valid ? null : (is_string($face['error'] ?? null) ? $face['error'] : 'encoding_failed'),
                'antispoof' => $antiSpoofEnabled && is_array($face['antispoof'] ?? null) ? $face['antispoof'] : null,
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
     * Periksa secara cepat apakah bingkai pratinjau kamera pendaftaran wajah
     * memenuhi kriteria mutu (cahaya, ketajaman/tidak buram, 1 orang, posisi di
     * tengah oval) serta sesuai dengan arahan sudut pose (`lurus`, `kiri`,
     * `kanan`, `atas`, `bawah`) sebelum memotret.
     *
     * Memakai `need_embeddings=0` sehingga mesin wajah hanya menjalankan
     * detektor + 5 titik wajah + pengukuran Laplacian (~10-15 ms) tanpa
     * membebani CPU dengan model ArcFace.
     *
     * @return array{ok: bool, status: string, message: string, yaw?: float|null, yaw_signed?: float|null, pitch?: float|null, sharpness?: float, brightness?: float}
     */
    public function checkEnrollmentFrame(string $contents, string $poseKey = 'lurus', bool $isMirrored = true): array
    {
        if ($contents === '') {
            return [
                'ok' => false,
                'status' => self::StatusInvalid,
                'message' => 'Bingkai kamera belum siap.',
            ];
        }

        $antiSpoof = Setting::antiSpoofingEnabled() ? 1 : 0;
        $queryString = http_build_query([
            'quality' => 1,
            'need_embeddings' => 0,
            'antispoof' => $antiSpoof,
        ]);

        try {
            $response = Http::connectTimeout(2)
                ->timeout(6)
                ->acceptJson()
                ->attach('image', $contents, 'frame.jpg')
                ->post($this->serviceUrl().'/detect?'.$queryString);
        } catch (ConnectionException) {
            return [
                'ok' => false,
                'status' => self::StatusUnavailable,
                'message' => 'Layanan deteksi wajah belum terhubung.',
            ];
        }

        if (! $response->successful()) {
            return [
                'ok' => false,
                'status' => self::StatusInvalid,
                'message' => 'Gagal memeriksa posisi wajah.',
            ];
        }

        $data = $response->json();
        if (! is_array($data)) {
            return [
                'ok' => false,
                'status' => self::StatusInvalid,
                'message' => 'Balasan layanan wajah tidak dikenali.',
            ];
        }

        $status = (string) ($data['status'] ?? '');
        $faces = (array) ($data['faces'] ?? []);

        if ($status === self::StatusNoFace || $status === 'no_face' || $faces === []) {
            return [
                'ok' => false,
                'status' => self::StatusNoFace,
                'message' => 'Wajah belum terdeteksi. Posisikan wajah di dalam lingkaran oval.',
            ];
        }

        if (count($faces) > 1) {
            return [
                'ok' => false,
                'status' => self::StatusMultipleFaces,
                'message' => 'Terdeteksi '.count($faces).' wajah. Pastikan hanya Anda sendiri di depan kamera.',
            ];
        }

        $face = is_array($faces[0] ?? null) ? $faces[0] : [];
        $box = is_array($face['bbox'] ?? null) ? $face['bbox'] : [];
        $quality = is_array($face['quality'] ?? null) ? $face['quality'] : [];
        $antispoof = is_array($face['antispoof'] ?? null) ? $face['antispoof'] : null;

        if ($antiSpoof === 1 && (($face['error'] ?? '') === 'spoof_detected' || ($antispoof !== null && empty($antispoof['is_live'])))) {
            $spoofLabel = (string) ($antispoof['label'] ?? 'Layar/Foto Palsu');

            return [
                'ok' => false,
                'status' => self::StatusSpoofDetected,
                'message' => 'Terdeteksi '.$spoofLabel.'. Wajib menggunakan wajah asli secara langsung.',
            ];
        }

        $width = max(1, (int) ($data['width'] ?? 640));
        $height = max(1, (int) ($data['height'] ?? 480));
        $faceSize = (int) ($face['face_size'] ?? 0);
        $minSide = max(1, min($width, $height));
        $sizeRatio = $faceSize / $minSide;

        if ($faceSize < 80 || $sizeRatio < 0.28) {
            return [
                'ok' => false,
                'status' => self::StatusFaceTooSmall,
                'message' => 'Wajah terlalu jauh. Dekatkan wajah agar pas di dalam lingkaran oval.',
            ];
        }

        if ($sizeRatio > 0.76) {
            return [
                'ok' => false,
                'status' => 'face_too_close',
                'message' => 'Wajah terlalu dekat. Mundurkan sedikit agar masuk ke dalam lingkaran oval.',
            ];
        }

        // Periksa secara ketat apakah seluruh wajah berada di dalam lingkaran oval tengah
        if ($box !== []) {
            $left = (int) ($box['left'] ?? 0);
            $right = (int) ($box['right'] ?? $width);
            $top = (int) ($box['top'] ?? 0);
            $bottom = (int) ($box['bottom'] ?? $height);

            $cx = (($left + $right) / 2.0) / $width;
            $cy = (($top + $bottom) / 2.0) / $height;
            $leftRatio = $left / $width;
            $rightRatio = $right / $width;
            $topRatio = $top / $height;
            $bottomRatio = $bottom / $height;

            if (
                abs($cx - 0.5) > 0.13
                || abs($cy - 0.5) > 0.15
                || $leftRatio < 0.20
                || $rightRatio > 0.80
                || $topRatio < 0.08
                || $bottomRatio > 0.90
            ) {
                return [
                    'ok' => false,
                    'status' => 'off_center',
                    'message' => 'Wajah berada di luar lingkaran oval. Posisikan wajah tepat di tengah oval.',
                ];
            }
        }

        $brightness = (float) ($quality['brightness'] ?? 120.0);
        $sharpness = (float) ($quality['sharpness'] ?? 50.0);
        $yaw = isset($quality['yaw']) ? (float) $quality['yaw'] : null;
        $yawSigned = isset($quality['yaw_signed']) ? (float) $quality['yaw_signed'] : null;
        $roll = isset($quality['roll']) ? (float) $quality['roll'] : null;
        $pitch = isset($quality['pitch']) ? (float) $quality['pitch'] : null;

        if ($brightness > 0 && $brightness < 62.0) {
            return [
                'ok' => false,
                'status' => self::StatusTooDark,
                'message' => 'Cahaya terlalu gelap. Cari tempat yang lebih terang atau nyalakan lampu.',
            ];
        }

        if ($brightness > 228.0) {
            return [
                'ok' => false,
                'status' => self::StatusTooBright,
                'message' => 'Cahaya terlalu silau. Jangan menghadap/membelakangi lampu langsung.',
            ];
        }

        if ($sharpness > 0 && $sharpness < 24.0) {
            return [
                'ok' => false,
                'status' => self::StatusBlurry,
                'message' => 'Gambar buram atau goyang. Pegang kamera dengan tenang.',
            ];
        }

        if ($roll !== null && $roll > 0.30) {
            return [
                'ok' => false,
                'status' => self::StatusBadPose,
                'message' => 'Kepala terlalu miring. Tegakkan posisi kepala Anda.',
            ];
        }

        // Batas luar agar foto yang lolos di sini dijamin lolos saat dikirim ke antrean
        if ($yaw !== null && $yaw > 0.48) {
            return [
                'ok' => false,
                'status' => self::StatusBadPose,
                'message' => 'Wajah menengok terlalu jauh ke samping. Kurangi sedikit sudut tengokan.',
            ];
        }

        if ($pitch !== null && ($pitch < 0.26 || $pitch > 0.78)) {
            return [
                'ok' => false,
                'status' => self::StatusBadPose,
                'message' => 'Sudut kepala terlalu ekstrem. Kurangi sedikit dongakan atau tundukan.',
            ];
        }

        // Evaluasi kesesuaian arah hadap terhadap pose yang diminta.
        // Pada kamera depan (isMirrored = true), tampilan layar dicerminkan secara horizontal:
        // - menengok ke KIRI layar menghasilkan yaw_signed > 0 pada gambar mentah (screenYaw < 0)
        // - menengok ke KANAN layar menghasilkan yaw_signed < 0 pada gambar mentah (screenYaw > 0)
        $screenYaw = $yawSigned !== null
            ? ($isMirrored ? -$yawSigned : $yawSigned)
            : null;

        $pose = mb_strtolower(trim($poseKey));

        if ($pose === 'lurus' || str_contains($pose, 'lurus')) {
            if ($yaw !== null && $yaw > 0.12) {
                return [
                    'ok' => false,
                    'status' => 'wrong_pose',
                    'message' => 'Wajah masih menengok ke samping. Hadapkan lurus ke depan kamera.',
                ];
            }
            if ($pitch !== null && $pitch < 0.38) {
                return [
                    'ok' => false,
                    'status' => 'wrong_pose',
                    'message' => 'Wajah masih mendongak. Hadapkan lurus ke depan kamera.',
                ];
            }
            if ($pitch !== null && $pitch > 0.63) {
                return [
                    'ok' => false,
                    'status' => 'wrong_pose',
                    'message' => 'Wajah masih menunduk. Hadapkan lurus ke depan kamera.',
                ];
            }
        } elseif ($pose === 'kiri' || str_contains($pose, 'kiri')) {
            if ($screenYaw !== null && $screenYaw > 0.08) {
                return [
                    'ok' => false,
                    'status' => 'wrong_pose',
                    'message' => 'Arah terbalik! Anda menengok ke kanan, silakan tengok ke kiri.',
                ];
            }
            if (($screenYaw !== null && $screenYaw > -0.13) || ($yaw !== null && $yaw < 0.13)) {
                return [
                    'ok' => false,
                    'status' => 'wrong_pose',
                    'message' => 'Wajah masih menghadap lurus. Tengokkan kepala sedikit ke kiri.',
                ];
            }
        } elseif ($pose === 'kanan' || str_contains($pose, 'kanan')) {
            if ($screenYaw !== null && $screenYaw < -0.08) {
                return [
                    'ok' => false,
                    'status' => 'wrong_pose',
                    'message' => 'Arah terbalik! Anda menengok ke kiri, silakan tengok ke kanan.',
                ];
            }
            if (($screenYaw !== null && $screenYaw < 0.13) || ($yaw !== null && $yaw < 0.13)) {
                return [
                    'ok' => false,
                    'status' => 'wrong_pose',
                    'message' => 'Wajah masih menghadap lurus. Tengokkan kepala sedikit ke kanan.',
                ];
            }
        } elseif ($pose === 'atas' || str_contains($pose, 'atas') || str_contains($pose, 'mendongak')) {
            if ($yaw !== null && $yaw > 0.20) {
                return [
                    'ok' => false,
                    'status' => 'wrong_pose',
                    'message' => 'Hadapkan wajah ke depan sambil mengangkat dagu sedikit ke atas.',
                ];
            }
            if ($pitch !== null && $pitch > 0.56) {
                return [
                    'ok' => false,
                    'status' => 'wrong_pose',
                    'message' => 'Arah terbalik! Anda menunduk ke bawah, silakan angkat dagu sedikit ke atas.',
                ];
            }
            if ($pitch !== null && $pitch > 0.47) {
                return [
                    'ok' => false,
                    'status' => 'wrong_pose',
                    'message' => 'Wajah masih lurus. Angkat kepala/dagu sedikit ke atas.',
                ];
            }
        } elseif ($pose === 'bawah' || str_contains($pose, 'bawah') || str_contains($pose, 'menunduk')) {
            if ($yaw !== null && $yaw > 0.20) {
                return [
                    'ok' => false,
                    'status' => 'wrong_pose',
                    'message' => 'Hadapkan wajah ke depan sambil menundukkan kepala sedikit.',
                ];
            }
            if ($pitch !== null && $pitch < 0.45) {
                return [
                    'ok' => false,
                    'status' => 'wrong_pose',
                    'message' => 'Arah terbalik! Anda mendongak ke atas, silakan tundukkan kepala sedikit ke bawah.',
                ];
            }
            if ($pitch !== null && $pitch < 0.55) {
                return [
                    'ok' => false,
                    'status' => 'wrong_pose',
                    'message' => 'Wajah masih lurus. Tundukkan kepala sedikit ke bawah.',
                ];
            }
        }

        return [
            'ok' => true,
            'status' => 'ok',
            'message' => 'Posisi pas! Tahan posisi 3 detik...',
            'yaw' => $yaw,
            'yaw_signed' => $yawSigned,
            'pitch' => $pitch,
            'sharpness' => $sharpness,
            'brightness' => $brightness,
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
        if (! app()->runningUnitTests()) {
            $cached = Cache::get('face_service_health_cache');
            if (is_array($cached) && array_key_exists('result', $cached)) {
                return $cached['result'];
            }
        }

        $result = $this->fetchHealth();

        if (! app()->runningUnitTests()) {
            Cache::put('face_service_health_cache', ['result' => $result], 3);
        }

        return $result;
    }

    /**
     * @return array{model: string, ready: bool, dimensions: int}|null
     */
    private function fetchHealth(): ?array
    {
        try {
            $response = Http::connectTimeout(2)->timeout(3)->acceptJson()->get($this->serviceUrl().'/health');
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
        $query = [
            'require_single' => 1,
            'antispoof' => Setting::antiSpoofingEnabled() ? 1 : 0,
        ];

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
