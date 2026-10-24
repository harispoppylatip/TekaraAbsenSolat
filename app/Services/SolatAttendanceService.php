<?php

namespace App\Services;

use App\Models\PrayerTime;
use App\Models\SolatAttendanceLog;
use App\Models\SolatSession;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Penulis tunggal catatan kehadiran solat.
 *
 * Semua jalan masuk kehadiran lewat sini: kamera gerbang, kamera petugas di
 * halaman absen, dan pencatatan manual oleh admin. Kalau aturannya disalin di
 * masing-masing pengendali, cepat atau lambat kehadiran tercatat dengan aturan
 * yang berbeda.
 */
class SolatAttendanceService
{
    public const StatusRecorded = 'recorded';

    public const StatusLate = 'late';

    public const StatusDuplicate = 'duplicate';

    public const StatusUnknown = 'unknown';

    /** Wajah mirip dengan lebih dari satu anggota sehingga tidak diputuskan. */
    public const StatusAmbiguous = 'ambiguous';

    public const StatusNoSession = 'no_session';

    public const StatusNoFace = 'no_face';

    /** Wajah ada, tetapi terlalu kecil di dalam bingkai foto. */
    public const StatusFaceTooSmall = 'face_too_small';

    public const StatusCrowded = 'crowded';

    public const StatusUnavailable = 'unavailable';

    public const StatusInvalid = 'invalid';

    /** Balasan bingkai berhasil dibaca; tiap wajah diperiksa sendiri sendiri. */
    public const StatusSuccessMulti = 'success';

    /**
     * Label untuk wajah yang belum berhasil dipastikan identitasnya.
     *
     * Ditulis dari sisi server supaya setiap pemanggil menampilkan teks yang
     * sama, tanpa perlu menyusun sendiri kalimat penggantinya.
     */
    public const LabelUnknown = 'Tidak dikenal';

    /**
     * Penyedia foto bukti bersama, dipakai selama satu bingkai diproses.
     *
     * @var (\Closure(): ?string)|null
     */
    private ?\Closure $sharedProof = null;

    public function __construct(
        private FaceEncodingService $encoder,
        private FaceMatcherService $matcher,
        private SolatSessionService $sessions,
        private SolatProofService $proofs,
    ) {}

    /**
     * Catat kehadiran SELURUH wajah yang terlihat di satu bingkai kamera.
     *
     * Setiap wajah diputuskan sendiri sendiri: satu wajah yang tidak dikenal,
     * terlalu kecil, atau gagal diproses tidak pernah menghentikan wajah lain.
     * Identitas datang dari angka wajah masing masing, BUKAN dari urutan wajah
     * di dalam bingkai, jadi orang yang bergeser posisinya tetap tercatat
     * sebagai dirinya sendiri.
     *
     * Satu yang perlu ditegaskan: nama yang dikirim peramban tidak pernah
     * dipercaya. Yang menentukan identitas hanyalah hasil pencocokan angka
     * wajah di sini.
     *
     * @return array<string, mixed> balasan berisi `faces` dengan satu entri per wajah
     */
    public function recordFacesFromImage(
        string $contents,
        ?string $deviceId = null,
        ?CarbonInterface $at = null,
        bool $allowTestSession = false,
    ): array {
        $session = $this->resolveSession($allowTestSession);

        if ($session === null) {
            return [
                'ok' => false,
                'status' => self::StatusNoSession,
                'message' => 'Belum ada sesi absen yang dibuka. Minta petugas membuka sesi waktu solat lebih dulu.',
                'session' => null,
                'faces' => [],
            ];
        }

        $detected = $this->encoder->detectContents($contents, (bool) config('solat.keep_proof_photo', true));

        if ($detected['status'] !== FaceEncodingService::StatusSuccess) {
            return [
                'ok' => false,
                'status' => $detected['status'],
                'message' => $detected['message'],
                'session' => $this->sessionPayload($session),
                'width' => $detected['width'],
                'height' => $detected['height'],
                'faces' => [],
            ];
        }

        // Berkas bukti disimpan SEKALI untuk seluruh bingkai, lalu dipakai
        // bersama oleh catatan absen yang lahir dari bingkai ini. Penyimpanannya
        // ditunda sampai ada catatan yang benar benar lahir: kalau tidak ada satu
        // pun wajah yang lolos, tidak boleh ada berkas yang tertinggal tanpa
        // catatan.
        $this->sharedProof = $this->sharedProofProvider($detected['photo'], $session, $at);
        $faces = [];
        $isCctv = $deviceId !== null && str_starts_with($deviceId, 'cctv-');
        $minFaceSize = $isCctv
            ? (int) config('face.cctv_min_face_size', 40)
            : (int) config('face.min_face_size', 80);
        $customTolerance = $isCctv
            ? (float) config('face.cctv_tolerance', 1.15)
            : null;

        try {
            foreach ($detected['faces'] as $face) {
                $faces[] = $this->judgeFace(
                    face: $face,
                    minFaceSize: $minFaceSize,
                    session: $session,
                    deviceId: $deviceId,
                    at: $at,
                    customTolerance: $customTolerance,
                );
            }
        } finally {
            $this->sharedProof = null;
        }

        $recorded = count(array_filter($faces, static fn (array $face): bool => $face['attendance']['recorded'] === true));

        // Foto bukti bingkai ini ditampilkan di halaman supaya petugas bisa
        // langsung memastikan orang yang tercatat memang yang di depan kamera.
        // Alamatnya dibuat dari catatan absen pertama yang lahir dari bingkai ini.
        $proofLog = null;

        foreach ($faces as $face) {
            if ($face['log_id'] !== null) {
                $proofLog = SolatAttendanceLog::find($face['log_id']);
                break;
            }
        }

        return [
            'ok' => $recorded > 0,
            'status' => self::StatusSuccessMulti,
            'message' => $recorded === 0
                ? 'Tidak ada kehadiran yang tercatat dari bingkai ini.'
                : $recorded.' kehadiran tercatat dari '.count($faces).' wajah.',
            'session' => $this->sessionPayload($session),
            'width' => $detected['width'],
            'height' => $detected['height'],
            'proof_url' => $proofLog === null ? null : $this->proofs->url($proofLog),
            'faces' => $faces,
        ];
    }

    /**
     * Putuskan satu wajah: dicatat, sudah tercatat, atau ditolak beserta alasannya.
     *
     * @param  array<string, mixed>  $face
     * @return array<string, mixed>
     */
    private function judgeFace(
        array $face,
        int $minFaceSize,
        SolatSession $session,
        ?string $deviceId,
        ?CarbonInterface $at,
        ?float $customTolerance = null,
    ): array {
        $box = [
            'top' => (int) $face['top'],
            'right' => (int) $face['right'],
            'bottom' => (int) $face['bottom'],
            'left' => (int) $face['left'],
        ];

        // Wajah yang angkanya gagal dihitung tidak bisa diputuskan, tetapi
        // kotaknya tetap dikirim supaya peramban bisa menggambarnya dan
        // menandainya belum dikenal.
        if (($face['encoding'] ?? null) === null) {
            return $this->faceReport(
                box: $box,
                label: self::LabelUnknown,
                status: self::StatusNoFace,
                display: 'Wajah tak jelas',
                detail: 'Belum terdaftar',
                distance: null,
            );
        }

        if ($minFaceSize > 0 && (int) $face['face_size'] > 0 && (int) $face['face_size'] < $minFaceSize) {
            return $this->faceReport(
                box: $box,
                label: self::LabelUnknown,
                status: self::StatusFaceTooSmall,
                display: 'Dekatkan wajah',
                detail: 'Dekatkan wajah',
                distance: null,
            );
        }

        $match = $this->matcher->match($face['encoding'], $customTolerance);

        // Dua anggota sama sama mirip: belum bisa diputuskan, jadi tidak ada
        // identitas yang dipilih dan tidak ada kehadiran yang dicatat.
        if ($match['status'] === FaceMatcherService::StatusAmbiguous) {
            return $this->faceReport(
                box: $box,
                label: self::LabelUnknown,
                status: self::StatusAmbiguous,
                display: 'Belum pasti',
                detail: 'Ulangi pindai',
                distance: $match['distance'],
            );
        }

        if ($match['status'] !== FaceMatcherService::StatusMatched || $match['user'] === null) {
            return $this->faceReport(
                box: $box,
                label: self::LabelUnknown,
                status: self::StatusUnknown,
                display: 'Belum terdaftar',
                detail: 'Belum terdaftar',
                distance: $match['distance'],
            );
        }

        /** @var User $user */
        $user = $match['user'];

        $result = $this->record($user, $session, $match['distance'], $deviceId, $at);

        if ($result['ok']) {
            return $this->faceReport(
                box: $box,
                label: $user->name,
                status: (string) $result['status'],
                display: (string) $result['display_message'],
                detail: (string) $user->identifier_number,
                distance: $match['distance'],
                user: $user,
                recorded: true,
                logId: $result['log'] === null ? null : (int) $result['log']->getKey(),
                message: (string) $result['message'],
            );
        }

        // Sudah tercatat sebelumnya, atau percobaan yang masih dalam jeda. Kedua
        // keadaan itu bukan kegagalan, jadi tetap ditampilkan sebagai nama orang
        // tersebut supaya petugas tahu siapa yang sedang berdiri di depan kamera.
        return $this->faceReport(
            box: $box,
            label: $user->name,
            status: (string) $result['status'],
            display: (string) $result['display_message'],
            detail: (string) $user->identifier_number,
            distance: $match['distance'],
            user: $user,
            logId: $result['log'] === null ? null : (int) $result['log']->getKey(),
            message: (string) $result['message'],
        );
    }

    /**
     * Bentuk satu entri wajah pada balasan halaman kamera.
     *
     * Isinya sengaja pipih (tanpa bersarang) supaya peramban tidak perlu
     * menelusuri banyak lapisan saat menggambar kotak dan label.
     *
     * @param  array{top: int, right: int, bottom: int, left: int}  $box
     * @return array<string, mixed>
     */
    private function faceReport(
        array $box,
        ?string $label,
        string $status,
        string $display,
        string $detail,
        ?float $distance,
        ?User $user = null,
        bool $recorded = false,
        ?int $logId = null,
        string $message = '',
    ): array {
        return [
            'box' => $box,
            'label' => $label,
            'status' => $status,
            'display_message' => $display,
            'display_detail' => $detail,
            'member' => $user === null ? null : [
                'id' => $user->getKey(),
                'name' => $user->name,
                'identifier_number' => $user->identifier_number,
                'class_name' => $user->class_name,
            ],
            'distance' => $distance,
            'log_id' => $logId,
            'attendance' => [
                'recorded' => $recorded,
                'status' => $status,
                'display_message' => $display,
                'message' => $message === '' ? $display : $message,
            ],
        ];
    }

    /**
     * Ringkas keadaan sesi untuk balasan.
     *
     * @return array<string, mixed>|null
     */
    private function sessionPayload(SolatSession $session): array
    {
        return [
            'id' => $session->getKey(),
            'prayer_name' => $session->prayer_name,
            'label' => $session->prayerLabel(),
            'is_test' => $session->isTest(),
            'total' => $session->logs()->count(),
        ];
    }

    /**
     * Catat kehadiran dari sebuah foto wajah.
     *
     * `$allowTestSession` hanya dipakai kamera petugas di halaman absen wajah.
     * Kamera gerbang dan perangkat sensor tetap menunggu sesi waktu solat yang
     * sungguhan, karena absen yang masuk dari sana dihitung sebagai kehadiran.
     *
     * @return array<string, mixed>
     */
    public function recordFromImage(
        string $contents,
        ?string $deviceId = null,
        ?CarbonInterface $at = null,
        bool $allowTestSession = false,
    ): array {
        $session = $this->resolveSession($allowTestSession);

        if ($session === null) {
            return $this->reply(
                status: self::StatusNoSession,
                ok: false,
                label: 'Sesi belum dibuka',
                message: 'Belum ada sesi absen yang dibuka. Minta petugas membuka sesi waktu solat lebih dulu.',
                display: 'Sesi belum buka',
                session: null,
            );
        }

        $encoded = $this->encoder->encodeContents($contents, (bool) config('solat.keep_proof_photo', true));

        if ($encoded['status'] !== FaceEncodingService::StatusSuccess) {
            return match ($encoded['status']) {
                FaceEncodingService::StatusNoFace => $this->reply(
                    status: self::StatusNoFace,
                    ok: false,
                    label: 'Wajah tidak terbaca',
                    message: $encoded['message'],
                    display: 'Wajah tak jelas',
                    session: $session,
                ),
                FaceEncodingService::StatusMultipleFaces => $this->reply(
                    status: self::StatusCrowded,
                    ok: false,
                    label: 'Wajah lebih dari satu',
                    message: $encoded['message'],
                    display: 'Satu wajah saja',
                    session: $session,
                ),
                FaceEncodingService::StatusFaceTooSmall => $this->reply(
                    status: self::StatusFaceTooSmall,
                    ok: false,
                    label: 'Wajah terlalu kecil',
                    message: $encoded['message'],
                    display: 'Dekatkan wajah',
                    session: $session,
                ),
                FaceEncodingService::StatusUnavailable => $this->reply(
                    status: self::StatusUnavailable,
                    ok: false,
                    label: 'Layanan wajah mati',
                    message: $encoded['message'],
                    display: 'Layanan mati',
                    session: $session,
                ),
                default => $this->reply(
                    status: self::StatusInvalid,
                    ok: false,
                    label: 'Foto tidak terbaca',
                    message: $encoded['message'],
                    display: 'Foto tak terbaca',
                    session: $session,
                ),
            };
        }

        if (($encoded['face_count'] ?? 1) > 1) {
            return $this->reply(
                status: self::StatusCrowded,
                ok: false,
                label: 'Wajah lebih dari satu',
                message: 'Ada lebih dari satu wajah di depan kamera. Absen satu orang saja supaya tidak salah catat.',
                display: 'Satu wajah saja',
                session: $session,
            );
        }

        $match = $this->matcher->match($encoded['encoding']);

        if ($match['status'] === FaceMatcherService::StatusAmbiguous) {
            return $this->reply(
                status: self::StatusAmbiguous,
                ok: false,
                label: 'Wajah ambigu',
                message: 'Wajah ini mirip dengan lebih dari satu anggota sehingga belum bisa dipastikan. Ulangi pindainya dengan cahaya yang cukup, atau minta admin memeriksa data wajah anggota tersebut.',
                display: 'Wajah ambigu',
                session: $session,
            );
        }

        if ($match['status'] !== FaceMatcherService::StatusMatched || $match['user'] === null) {
            return $this->reply(
                status: self::StatusUnknown,
                ok: false,
                label: 'Wajah belum dikenali',
                message: $match['reason'] === FaceMatcherService::ReasonNoReference
                    ? 'Belum ada satu pun wajah anggota yang terdaftar. Minta admin mendaftarkan wajah lebih dulu di halaman Data Wajah.'
                    : 'Wajah ini belum cocok dengan data wajah mana pun. Dekatkan wajah ke kamera, pastikan cahaya cukup, atau minta admin memeriksa data wajah.',
                display: 'Belum terdaftar',
                session: $session,
            );
        }

        return $this->record($match['user'], $session, $match['distance'], $deviceId, $at, $encoded['photo'] ?? null);
    }

    /**
     * Sesi yang menerima catatan absen.
     *
     * Sesi uji coba berada di tabel yang sama dengan sesi waktu solat, jadi
     * hanya kamera petugas yang boleh memakainya. Perangkat gerbang tetap
     * diarahkan ke sesi waktu solat supaya tidak ada absen percobaan yang
     * tercampur ke kehadiran yang sungguhan.
     */
    private function resolveSession(bool $allowTestSession): ?SolatSession
    {
        $session = $this->sessions->activeSession();

        if ($session !== null || ! $allowTestSession) {
            return $session;
        }

        $test = $this->sessions->testSession();

        return $test?->isOpen() === true ? $test : null;
    }

    /**
     * Catat kehadiran seorang anggota pada sesi tertentu.
     *
     * `$photo` berisi isi berkas JPEG foto bukti, bila mesin wajah mengirimnya.
     * `$proofPath` dipakai bila foto buktinya sudah tersimpan lebih dulu, yaitu
     * pada absen banyak wajah sekaligus: satu foto bingkai dipakai bersama oleh
     * seluruh catatan yang lahir dari bingkai itu. Bukti foto hanya pelengkap:
     * bila penyimpanannya gagal, absennya tetap tercatat dan kegagalannya hanya
     * muncul di catatan galat aplikasi.
     *
     * @return array<string, mixed>
     */
    public function record(
        User $user,
        SolatSession $session,
        ?float $distance = null,
        ?string $deviceId = null,
        ?CarbonInterface $at = null,
        ?string $photo = null,
        ?string $proofPath = null,
    ): array {
        $moment = $at !== null ? Carbon::parse($at) : Carbon::now();

        $existing = SolatAttendanceLog::query()
            ->where('solat_session_id', $session->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing !== null) {
            if ($session->isTest()) {
                $left = $this->cooldownLeft($existing, $moment);

                if ($left !== null) {
                    return $this->reply(
                        status: self::StatusDuplicate,
                        ok: false,
                        label: 'Jeda pindai',
                        message: $user->name.' sudah mencoba pukul '.$existing->scanned_at?->format('H:i')
                            .'. Jeda mode uji coba masih sekitar '.$left.' menit.',
                        display: 'Tunggu '.$left.' menit',
                        session: $session,
                        user: $user,
                        log: $existing,
                        distance: $distance,
                    );
                }

                return $this->refresh($existing, $user, $session, $distance, $deviceId, $moment, $photo, $proofPath);
            }

            return $this->reply(
                status: self::StatusDuplicate,
                ok: false,
                label: 'Sudah absen',
                message: $user->name.' sudah tercatat '.mb_strtolower($existing->statusLabel()).' pukul '
                    .$existing->scanned_at?->format('H:i').'.',
                display: 'Sudah absen',
                session: $session,
                user: $user,
                log: $existing,
                distance: $distance,
            );
        }

        $status = $this->statusFor($session, $moment);
        $late = $status === SolatAttendanceLog::StatusTerlambat;

        $log = SolatAttendanceLog::create([
            'user_id' => $user->id,
            'solat_session_id' => $session->id,
            'scanned_at' => $moment,
            'status' => $status,
            'match_distance' => $distance,
            'device_id' => $deviceId,
            'proof_photo_path' => $proofPath ?? $this->keepProof($photo, $session, $moment),
        ]);

        $via = $log->isFingerprint() ? ' via sidik jari' : '';

        return $this->reply(
            status: $late ? self::StatusLate : self::StatusRecorded,
            ok: true,
            label: $late ? 'Terlambat' : 'Hadir',
            message: $session->isTest()
                ? 'Percobaan '.$user->name.($log->isFingerprint() ? ' absen via sidik jari' : '').' tercatat pukul '.$moment->format('H:i').'. Data ini tidak dihitung pada rekap.'
                : $user->name.' tercatat '.($late ? 'terlambat' : 'hadir').$via.' pada solat '
                    .$session->prayerLabel().' pukul '.$moment->format('H:i').'.',
            display: $late ? 'Terlambat' : 'Absen tersimpan',
            session: $session,
            user: $user,
            log: $log,
            distance: $distance,
        );
    }

    /**
     * Perbarui catatan percobaan yang sudah ada pada sesi uji coba.
     *
     * Indeks unik (sesi, anggota) tetap dijaga, jadi percobaan ulang sesudah
     * jeda memperbarui baris yang sama, bukan menumpuk baris baru. Foto bukti
     * lama dibuang supaya tidak ada berkas tanpa catatan.
     *
     * @return array<string, mixed>
     */
    private function refresh(
        SolatAttendanceLog $log,
        User $user,
        SolatSession $session,
        ?float $distance,
        ?string $deviceId,
        CarbonInterface $moment,
        ?string $photo,
        ?string $proofPath = null,
    ): array {
        $status = $this->statusFor($session, $moment);
        $late = $status === SolatAttendanceLog::StatusTerlambat;

        // Foto bukti lama dibuang supaya tidak ada berkas tanpa catatan, TETAPI
        // hanya bila tidak ada catatan lain yang memakainya: pada absen banyak
        // wajah, satu foto bingkai dipakai bersama beberapa catatan.
        $lama = $this->proofs->path($log);
        $baru = $proofPath ?? $this->keepProof($photo, $session, $moment);

        if ($lama !== null && $lama !== $baru) {
            $this->proofs->forgetWhenOrphaned($lama, (int) $log->getKey());
        }

        $log->forceFill([
            'scanned_at' => $moment,
            'status' => $status,
            'match_distance' => $distance,
            'device_id' => $deviceId,
            'proof_photo_path' => $baru,
        ])->save();

        return $this->reply(
            status: $late ? self::StatusLate : self::StatusRecorded,
            ok: true,
            label: $late ? 'Terlambat' : 'Hadir',
            message: 'Percobaan '.$user->name.($log->isFingerprint() ? ' absen via sidik jari' : '').' diperbarui pukul '.$moment->format('H:i').'. Data ini tidak dihitung pada rekap.',
            display: $late ? 'Terlambat' : 'Uji coba OK',
            session: $session,
            user: $user,
            log: $log,
            distance: $distance,
        );
    }

    /**
     * Sisa menit jeda sebelum orang yang sama boleh dipindai lagi pada sesi uji
     * coba. Null berarti jedanya sudah lewat atau jedanya dimatikan.
     */
    private function cooldownLeft(SolatAttendanceLog $log, CarbonInterface $moment): ?int
    {
        $minutes = SolatSessionService::testCooldownMinutes();

        if ($minutes <= 0 || $log->scanned_at === null) {
            return null;
        }

        $wait = ($minutes * 60) - ($moment->getTimestamp() - $log->scanned_at->getTimestamp());

        if ($wait <= 0) {
            return null;
        }

        return (int) ceil($wait / 60);
    }

    /**
     * Hadir atau terlambat, dilihat dari jarak waktu absen terhadap waktu mulai
     * ditambah toleransi.
     */
    public function statusFor(SolatSession $session, CarbonInterface $at): string
    {
        $minutes = (int) (PrayerTime::query()
            ->where('prayer_name', $session->prayer_name)
            ->value('late_minutes') ?? config('solat.late_minutes', 10));

        $start = $this->sessions->moment($session, 'start_time');
        $limit = $start->copy()->addMinutes($minutes);

        return $at->lessThanOrEqualTo($limit)
            ? SolatAttendanceLog::StatusHadir
            : SolatAttendanceLog::StatusTerlambat;
    }

    /**
     * Simpan foto bukti bila ada dan bila fiturnya memang menyala.
     *
     * Kegagalan penyimpanan tidak pernah dilempar ke pemanggil. Bukti foto
     * berguna untuk menelusuri absen, sedangkan absennya sendiri yang utama.
     *
     * Saat satu bingkai memuat beberapa wajah, penyimpanannya hanya sekali dan
     * seluruh catatan dari bingkai itu memakai berkas yang sama.
     */
    private function keepProof(?string $photo, SolatSession $session, ?CarbonInterface $at = null): ?string
    {
        if ($this->sharedProof !== null) {
            return ($this->sharedProof)();
        }

        if ($photo === null || $photo === '' || ! config('solat.keep_proof_photo', true)) {
            return null;
        }

        try {
            return $this->proofs->store($photo, $session, $at ?? Carbon::now());
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * Sediakan foto bukti bersama untuk satu bingkai.
     *
     * Penyimpanannya ditunda sampai benar benar diminta, lalu hasilnya diingat
     * supaya seluruh wajah pada bingkai itu memakai satu berkas yang sama.
     *
     * @return \Closure(): ?string
     */
    private function sharedProofProvider(?string $photo, SolatSession $session, ?CarbonInterface $at): \Closure
    {
        $tersimpan = false;
        $path = null;

        return function () use (&$tersimpan, &$path, $photo, $session, $at): ?string {
            if (! $tersimpan) {
                $path = $this->keepProofTanpaBersama($photo, $session, $at);
                $tersimpan = true;
            }

            return $path;
        };
    }

    /**
     * Simpan satu berkas bukti tanpa memakai penyedia bersama.
     */
    private function keepProofTanpaBersama(?string $photo, SolatSession $session, ?CarbonInterface $at): ?string
    {
        if (! config('solat.keep_proof_photo', true)) {
            return null;
        }

        if ($photo === null || $photo === '') {
            return null;
        }

        try {
            return $this->proofs->store($photo, $session, $at ?? Carbon::now());
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * Susun satu bentuk balasan supaya halaman web dan layar perangkat
     * membaca hasil yang sama.
     *
     * @return array<string, mixed>
     */
    private function reply(
        string $status,
        bool $ok,
        string $label,
        string $message,
        string $display,
        ?SolatSession $session,
        ?User $user = null,
        ?SolatAttendanceLog $log = null,
        ?float $distance = null,
    ): array {
        return [
            'status' => $status,
            'ok' => $ok,
            'label' => $label,
            'message' => $message,
            'user' => $user,
            'log' => $log,
            'session' => $session,
            'distance' => $distance,
            'proof_url' => $this->proofs->url($log),
            'display_message' => $display,
            'display_detail' => $user !== null ? $user->name : 'Lapor operator',
        ];
    }
}
