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

    public const StatusNoSession = 'no_session';

    public const StatusNoFace = 'no_face';

    public const StatusCrowded = 'crowded';

    public const StatusUnavailable = 'unavailable';

    public const StatusInvalid = 'invalid';

    public function __construct(
        private FaceEncodingService $encoder,
        private FaceMatcherService $matcher,
        private SolatSessionService $sessions,
        private SolatProofService $proofs,
    ) {}

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

        $match = $this->matcher->identify($encoded['encoding']);

        if ($match === null) {
            return $this->reply(
                status: self::StatusUnknown,
                ok: false,
                label: 'Wajah belum dikenali',
                message: 'Wajah ini belum terdaftar. Minta admin mendaftarkan wajah lebih dulu di halaman Data Wajah.',
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
     * Bukti foto hanya pelengkap: bila penyimpanannya gagal, absennya tetap
     * tercatat dan kegagalannya hanya muncul di catatan galat aplikasi.
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

                return $this->refresh($existing, $user, $session, $distance, $deviceId, $moment, $photo);
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
            'proof_photo_path' => $this->keepProof($photo, $session, $moment),
        ]);

        return $this->reply(
            status: $late ? self::StatusLate : self::StatusRecorded,
            ok: true,
            label: $late ? 'Terlambat' : 'Hadir',
            message: $session->isTest()
                ? 'Percobaan '.$user->name.' tercatat pukul '.$moment->format('H:i').'. Data ini tidak dihitung pada rekap.'
                : $user->name.' tercatat '.($late ? 'terlambat' : 'hadir').' pada solat '
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
    ): array {
        $status = $this->statusFor($session, $moment);
        $late = $status === SolatAttendanceLog::StatusTerlambat;

        $this->proofs->forget($log->proof_photo_path);

        $log->forceFill([
            'scanned_at' => $moment,
            'status' => $status,
            'match_distance' => $distance,
            'device_id' => $deviceId,
            'proof_photo_path' => $this->keepProof($photo, $session, $moment),
        ])->save();

        return $this->reply(
            status: $late ? self::StatusLate : self::StatusRecorded,
            ok: true,
            label: $late ? 'Terlambat' : 'Hadir',
            message: 'Percobaan '.$user->name.' diperbarui pukul '.$moment->format('H:i').'. Data ini tidak dihitung pada rekap.',
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
     */
    private function keepProof(?string $photo, SolatSession $session, CarbonInterface $moment): ?string
    {
        if ($photo === null || $photo === '' || ! config('solat.keep_proof_photo', true)) {
            return null;
        }

        try {
            return $this->proofs->store($photo, $session, $moment);
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
