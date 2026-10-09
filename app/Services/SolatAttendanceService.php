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
    ) {}

    /**
     * Catat kehadiran dari sebuah foto wajah.
     *
     * @return array<string, mixed>
     */
    public function recordFromImage(string $contents, ?string $deviceId = null, ?CarbonInterface $at = null): array
    {
        $session = $this->sessions->activeSession();

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

        $encoded = $this->encoder->encodeContents($contents);

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

        return $this->record($match['user'], $session, $match['distance'], $deviceId, $at);
    }

    /**
     * Catat kehadiran seorang anggota pada sesi tertentu.
     *
     * @return array<string, mixed>
     */
    public function record(
        User $user,
        SolatSession $session,
        ?float $distance = null,
        ?string $deviceId = null,
        ?CarbonInterface $at = null,
    ): array {
        $moment = $at !== null ? Carbon::parse($at) : Carbon::now();

        $existing = SolatAttendanceLog::query()
            ->where('solat_session_id', $session->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing !== null) {
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
        ]);

        return $this->reply(
            status: $late ? self::StatusLate : self::StatusRecorded,
            ok: true,
            label: $late ? 'Terlambat' : 'Hadir',
            message: $user->name.' tercatat '.($late ? 'terlambat' : 'hadir').' pada solat '
                .$session->prayerLabel().' pukul '.$moment->format('H:i').'.',
            display: $late ? 'Terlambat' : 'Absen tersimpan',
            session: $session,
            user: $user,
            log: $log,
            distance: $distance,
        );
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
            'display_message' => $display,
            'display_detail' => $user !== null ? $user->name : 'Lapor operator',
        ];
    }
}
