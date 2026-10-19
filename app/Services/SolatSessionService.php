<?php

namespace App\Services;

use App\Models\PrayerTime;
use App\Models\SolatSession;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Pengatur sesi absen solat.
 *
 * Absen tidak digantungkan pada perbandingan jam di server, melainkan pada sesi
 * yang dibuka petugas. Alasannya: jadwal solat di sekolah sering digeser, dan
 * yang tahu keadaan sebenarnya adalah petugas yang berdiri di depan kamera.
 */
class SolatSessionService
{
    /**
     * Daftar hari dalam sepekan berdasarkan nomor hari ISO-8601 (1 = Senin .. 7 = Minggu).
     *
     * @var array<int, string>
     */
    public const DayLabels = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu',
    ];

    /**
     * @return array<int, string>
     */
    public static function dayOptions(): array
    {
        return self::DayLabels;
    }

    /**
     * Hari-hari aktif absen sekolah (nomor ISO 1..7).
     *
     * Dibaca dari kolom active_days pada daftar waktu solat, dan bila belum
     * diatur akan kembali ke bawaan aplikasi (Senin sampai Jumat).
     *
     * @return list<int>
     */
    public function activeDays(): array
    {
        $stored = PrayerTime::query()
            ->whereNotNull('active_days')
            ->orderBy('sort_order')
            ->first()
            ?->active_days;

        $raw = is_array($stored) && $stored !== []
            ? $stored
            : (array) config('solat.active_days', [1, 2, 3, 4, 5]);

        return $this->normalizeDays($raw);
    }

    /**
     * Simpan daftar hari aktif absen sekolah ke seluruh baris waktu solat.
     *
     * @param  array<int, int|string>  $days
     * @return list<int>
     */
    public function updateActiveDays(array $days): array
    {
        $normalized = $this->normalizeDays($days);

        if ($normalized === []) {
            $normalized = [1, 2, 3, 4, 5];
        }

        PrayerTime::query()->update([
            'active_days' => json_encode($normalized),
        ]);

        config(['solat.active_days' => $normalized]);

        return $normalized;
    }

    /**
     * Apakah tanggal tertentu termasuk hari aktif absen sekolah.
     */
    public function isActiveDay(CarbonInterface|string $date): bool
    {
        $dayIso = Carbon::parse($date)->dayOfWeekIso;

        return in_array($dayIso, $this->activeDays(), true);
    }

    /**
     * Ringkasan kalimat hari aktif dan hari libur absen.
     *
     * @return array{active_days: list<int>, active_labels: list<string>, off_labels: list<string>, summary: string}
     */
    public function activeDaysSummary(): array
    {
        $active = $this->activeDays();
        $activeLabels = [];
        $offLabels = [];

        foreach (self::DayLabels as $num => $label) {
            if (in_array($num, $active, true)) {
                $activeLabels[] = $label;
            } else {
                $offLabels[] = $label;
            }
        }

        $activeText = $activeLabels !== [] ? implode(', ', $activeLabels) : 'Tidak ada';
        $offText = $offLabels !== [] ? 'Libur: '.implode(', ', $offLabels) : 'Buka setiap hari';

        return [
            'active_days' => $active,
            'active_labels' => $activeLabels,
            'off_labels' => $offLabels,
            'summary' => 'Aktif: '.$activeText.' · '.$offText,
        ];
    }

    /**
     * @param  array<int, mixed>  $days
     * @return list<int>
     */
    private function normalizeDays(array $days): array
    {
        $valid = [];

        foreach ($days as $day) {
            $num = (int) $day;
            if ($num >= 1 && $num <= 7 && ! in_array($num, $valid, true)) {
                $valid[] = $num;
            }
        }

        sort($valid);

        return array_values($valid);
    }

    /**
     * Menit sebelum waktu mulai saat sesi sudah boleh dibuka.
     *
     * Angka ini dipakai bersama oleh batas buka petugas dan mesin sesi, jadi
     * mengubah pengaturannya tidak akan membuat keduanya berbeda pendapat.
     */
    public static function earlyOpenMinutes(): int
    {
        return (int) config('solat.early_open_minutes', 10);
    }

    /**
     * Jeda sebelum orang yang sama boleh tercatat lagi pada sesi uji coba.
     *
     * Angka ini dipakai bersama oleh mesin absen dan teks halaman, jadi yang
     * dibaca petugas sama dengan yang dihitung aplikasi.
     */
    public static function testCooldownMinutes(): int
    {
        return (int) config('solat.test_cooldown_minutes', 4);
    }

    /**
     * Buat sesi untuk semua waktu solat aktif pada tanggal tertentu.
     *
     * Sesi yang sudah ada tidak diganggu, jadi menekan tombol ini dua kali
     * tidak menghapus catatan kehadiran yang sudah masuk.
     *
     * Catatan penting: kolom `date` disimpan sebagai tanggal-jam
     * ("2026-10-09 00:00:00"), bukan "2026-10-09". Karena itu pencarian sesi
     * yang sudah ada WAJIB memakai forDate() yang membandingkan tanggal saja.
     * Pencocokan langsung where('date', '2026-10-09') tidak akan menemukan
     * baris yang sudah ada sehingga aplikasi mencoba menyisipkan baris kedua
     * dan bentrok dengan indeks unik (prayer_name, date).
     *
     * @return array{created: int, skipped: int, total: int}
     */
    public function generateForDate(CarbonInterface|string $date): array
    {
        $day = Carbon::parse($date)->toDateString();
        $prayers = PrayerTime::query()->active()->ordered()->get();
        $created = 0;

        foreach ($prayers as $prayer) {
            $session = SolatSession::query()
                ->where('prayer_name', $prayer->prayer_name)
                ->forDate($day)
                ->first();

            if ($session === null) {
                SolatSession::create([
                    'prayer_name' => $prayer->prayer_name,
                    'date' => $day,
                    'start_time' => $prayer->start_time,
                    'end_time' => $prayer->end_time,
                    'status' => SolatSession::StatusBelumDibuka,
                ]);

                $created++;
            }
        }

        return [
            'created' => $created,
            'skipped' => $prayers->count() - $created,
            'total' => $prayers->count(),
        ];
    }

    /**
     * Sesi yang sedang dibuka. Hanya satu sesi yang boleh terbuka pada satu
     * waktu supaya kehadiran tidak tercampur ke waktu solat lain.
     *
     * Sesi uji coba tidak masuk hitungan, sebab sesi itu tidak menerima absen
     * waktu solat yang sungguhan.
     */
    public function activeSession(): ?SolatSession
    {
        $now = Carbon::now();

        foreach ($this->staleSessions($now) as $stale) {
            $this->closeAutomatically($stale, $now, 'Sesi ini tertinggal dari tanggal sebelumnya.');
        }

        return SolatSession::query()->real()->open()->latest('opened_at')->first();
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function open(SolatSession $session, ?User $by = null): array
    {
        if ($session->isOpen()) {
            return ['ok' => false, 'message' => 'Sesi '.$session->prayerLabel().' '.$session->date?->translatedFormat('d M Y').' sudah dibuka.'];
        }

        if ($error = $this->openGuard($session, $by)) {
            return ['ok' => false, 'message' => $error];
        }

        $this->markOpened($session, $by, Carbon::now());

        return [
            'ok' => true,
            'message' => 'Sesi '.$session->prayerLabel().' dibuka. Anggota sudah bisa absen di depan kamera.',
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function close(SolatSession $session, ?User $by = null): array
    {
        if ($session->isClosed()) {
            return ['ok' => false, 'message' => 'Sesi '.$session->prayerLabel().' sudah ditutup.'];
        }

        $total = $session->logs()->count();

        $this->markClosed($session, $by, Carbon::now());

        return [
            'ok' => true,
            'message' => 'Sesi '.$session->prayerLabel().' ditutup dengan '.$total.' catatan kehadiran.',
        ];
    }

    /**
     * Boleh dibuka atau tidak. Null berarti boleh.
     *
     * Admin sengaja dibebaskan dari batas waktu supaya sesi yang tertinggal,
     * misalnya karena listrik mati, masih bisa dirapikan tanpa mengubah jam
     * server.
     */
    public function openGuard(SolatSession $session, ?User $by = null): ?string
    {
        $other = $this->activeSession();

        if ($other !== null && ! $other->is($session)) {
            return 'Sesi '.$other->prayerLabel().' masih dibuka. Tutup dulu sebelum membuka sesi lain.';
        }

        if ($by?->isAdmin()) {
            return null;
        }

        $now = Carbon::now();

        if (! $session->date?->isSameDay($now)) {
            return 'Petugas hanya bisa membuka sesi hari ini. Hubungi admin untuk tanggal lain.';
        }

        if (! $this->isActiveDay($session->date ?? $now)) {
            return 'Hari '.$session->date?->translatedFormat('l').' termasuk hari libur absen. Hubungi admin bila sesi ini perlu dibuka.';
        }

        $start = $this->moment($session, 'start_time');
        $end = $this->moment($session, 'end_time');

        if ($now->lessThan($start->copy()->subMinutes(self::earlyOpenMinutes()))) {
            return 'Sesi '.$session->prayerLabel().' baru bisa dibuka '.self::earlyOpenMinutes().' menit sebelum pukul '
                .$session->shortTime($session->start_time).'.';
        }

        if ($now->greaterThan($end)) {
            return 'Waktu '.$session->prayerLabel().' sudah lewat pukul '.$session->shortTime($session->end_time)
                .'. Hubungi admin bila sesi ini perlu dibuka.';
        }

        return null;
    }

    /**
     * Jalankan pembukaan dan penutupan sesi otomatis untuk satu titik waktu.
     *
     * Dijalankan penjadwal setiap menit, jadi petugas tidak perlu lagi mengingat
     * jam buka. Tombol buka dan tutup manual tetap ada sebagai jalan keluar,
     * misalnya saat jadwal sekolah berubah mendadak di tengah hari.
     *
     * Urutannya sengaja tiga tahap:
     *
     * 1. Sesi dari tanggal sebelumnya dirapikan supaya tidak menahan hari ini.
     * 2. Sesi hari ini yang jendelanya sudah lewat ditutup, termasuk sesi yang
     *    tadi dibuka petugas, supaya waktu solat berikutnya tidak terhalang.
     * 3. Sesi yang jendelanya sudah masuk dibuka, waktunya diambil dari daftar
     *    waktu solat yang berlaku saat itu sehingga perubahan admin menang.
     *
     * Yang tidak pernah diganggu mesin: sesi yang ditutup manusia tidak dibuka
     * lagi, dan sesi yang sengaja dibuka manusia setelah jendelanya berakhir
     * dibiarkan terbuka sampai ada yang menutupnya.
     *
     * @return array<int, array{action: string, message: string}>
     */
    public function runAutomatically(?CarbonInterface $now = null): array
    {
        $stamp = $now === null ? Carbon::now() : Carbon::parse($now);
        $actions = [];

        foreach ($this->staleSessions($stamp) as $session) {
            $actions[] = $this->closeAutomatically($session, $stamp, 'Sesi ini tertinggal dari tanggal sebelumnya.');
        }

        foreach ($this->endedSessions($stamp) as $session) {
            $actions[] = $this->closeAutomatically($session, $stamp);
        }

        if (! $this->isActiveDay($stamp)) {
            return $actions;
        }

        foreach (PrayerTime::query()->active()->ordered()->get() as $prayer) {
            $action = $this->openDueSession($prayer, $stamp);

            if ($action !== null) {
                $actions[] = $action;
            }
        }

        return $actions;
    }

    /**
     * Sesi dari tanggal sebelumnya yang masih terbuka atau menunggu giliran.
     * Sesi seperti ini menahan hari ini karena hanya satu sesi boleh terbuka.
     *
     * @return Collection<int, SolatSession>
     */
    private function staleSessions(CarbonInterface $stamp): Collection
    {
        return SolatSession::query()
            ->real()
            ->whereDate('date', '<', $stamp->toDateString())
            ->whereIn('status', [SolatSession::StatusSedangDibuka, SolatSession::StatusTerjadwal])
            ->get();
    }

    /**
     * Sesi hari ini yang jendelanya sudah lewat tetapi masih terbuka.
     *
     * Sesi yang sengaja dibuka manusia sesudah jendelanya berakhir dibiarkan,
     * karena itu tanda sesinya memang masih dipakai meski sudah lewat jam.
     *
     * @return Collection<int, SolatSession>
     */
    private function endedSessions(CarbonInterface $stamp): Collection
    {
        return SolatSession::query()
            ->forDate($stamp)
            ->real()
            ->open()
            ->get()
            ->filter(fn (SolatSession $session): bool => $stamp->greaterThan($this->moment($session, 'end_time')))
            ->reject(fn (SolatSession $session): bool => $this->openedByHandAfterWindow($session))
            ->values();
    }

    /**
     * Buat sesi bila jendelanya sudah masuk, lalu buka.
     *
     * @return array{action: string, message: string}|null
     */
    private function openDueSession(PrayerTime $prayer, CarbonInterface $stamp): ?array
    {
        $day = $stamp->toDateString();
        $opensAt = $this->momentFor($day, $prayer->start_time)->subMinutes(self::earlyOpenMinutes());
        $endsAt = $this->momentFor($day, $prayer->end_time);

        $session = SolatSession::query()
            ->where('prayer_name', $prayer->prayer_name)
            ->real()
            ->forDate($day)
            ->first();

        // Sesi yang belum pernah disentuh mengikuti jam terbaru dari daftar
        // waktu solat, jadi pergeseran jadwal oleh admin tetap menang.
        if ($session !== null && ! $this->touchedByHand($session)) {
            $session->forceFill([
                'start_time' => $prayer->start_time,
                'end_time' => $prayer->end_time,
            ])->save();
        }

        if ($stamp->lessThan($opensAt) || $stamp->greaterThan($endsAt)) {
            return null;
        }

        // Sesi yang sudah dibuka atau ditutup manusia tidak diganggu mesin.
        if ($session !== null && $this->touchedByHand($session)) {
            return null;
        }

        if ($session === null) {
            $session = SolatSession::create([
                'prayer_name' => $prayer->prayer_name,
                'date' => $day,
                'start_time' => $prayer->start_time,
                'end_time' => $prayer->end_time,
                'status' => SolatSession::StatusBelumDibuka,
            ]);
        }

        // Sudah dibuka mesin pada menit sebelumnya, tidak perlu apa apa lagi.
        if ($session->isOpen()) {
            return null;
        }

        $other = $this->activeSession();

        if ($other !== null && ! $other->is($session)) {
            return $this->markWaiting($session, $other);
        }

        $this->markOpened($session, null, $stamp);

        return [
            'action' => 'opened',
            'message' => 'Sesi '.$session->prayerLabel().' '.$session->date?->translatedFormat('d M Y')
                .' dibuka otomatis untuk jendela '.$session->windowLabel().'.',
        ];
    }

    /**
     * Tandai sesi sebagai menunggu giliran.
     *
     * Hanya satu sesi yang boleh terbuka, jadi sesi yang sudah masuk waktunya
     * tetapi terhalang sesi lain ditandai Terjadwal supaya di daftar sesi
     * kelihatan bahwa sesi ini menunggu, bukan terlupa.
     *
     * @return array{action: string, message: string}|null
     */
    private function markWaiting(SolatSession $session, SolatSession $blocker): ?array
    {
        if ($session->status === SolatSession::StatusTerjadwal) {
            return null;
        }

        $session->forceFill(['status' => SolatSession::StatusTerjadwal])->save();

        return [
            'action' => 'waiting',
            'message' => 'Sesi '.$session->prayerLabel().' menunggu: sesi '.$blocker->prayerLabel()
                .' masih terbuka. Tutup sesi itu supaya sesi ini bisa dibuka otomatis.',
        ];
    }

    /**
     * @return array{action: string, message: string}
     */
    private function closeAutomatically(SolatSession $session, CarbonInterface $stamp, ?string $reason = null): array
    {
        $total = $session->logs()->count();

        $this->markClosed($session, null, $stamp);

        return [
            'action' => 'closed',
            'message' => 'Sesi '.$session->prayerLabel().' '.$session->date?->translatedFormat('d M Y')
                .' ditutup otomatis dengan '.$total.' catatan kehadiran.'.($reason === null ? '' : ' '.$reason),
        ];
    }

    /**
     * Sesi yang belum pernah dibuka maupun ditutup manusia.
     */
    private function touchedByHand(SolatSession $session): bool
    {
        return $session->opened_by !== null || $session->closed_by !== null;
    }

    /**
     * Sengaja dibuka manusia sesudah jendela absennya berakhir.
     */
    private function openedByHandAfterWindow(SolatSession $session): bool
    {
        return $session->opened_by !== null
            && $session->opened_at !== null
            && $session->opened_at->greaterThan($this->moment($session, 'end_time'));
    }

    private function markOpened(SolatSession $session, ?User $by, CarbonInterface $stamp): void
    {
        $session->forceFill([
            'status' => SolatSession::StatusSedangDibuka,
            'opened_at' => $stamp,
            'opened_by' => $by?->id,
            'closed_at' => null,
            'closed_by' => null,
        ])->save();
    }

    private function markClosed(SolatSession $session, ?User $by, CarbonInterface $stamp): void
    {
        $session->forceFill([
            'status' => SolatSession::StatusSudahDitutup,
            'closed_at' => $stamp,
            'closed_by' => $by?->id,
        ])->save();
    }

    /**
     * Gabungkan tanggal sesi dengan salah satu kolom jamnya menjadi satu titik
     * waktu penuh.
     */
    public function moment(SolatSession $session, string $column): Carbon
    {
        return $this->momentFor($session->date?->toDateString(), $session->{$column});
    }

    /**
     * Gabungkan tanggal dengan jam tertentu menjadi satu titik waktu penuh.
     */
    public function momentFor(string $day, ?string $time): Carbon
    {
        return Carbon::parse($day.' '.$time);
    }

    public function canOpen(SolatSession $session, ?User $by = null): bool
    {
        return $this->openGuard($session, $by) === null && ! $session->isOpen();
    }

    /**
     * Sesi uji coba yang sedang dipakai, bila ada.
     *
     * Sesi uji coba dipakai admin untuk mencoba kamera absen wajah tanpa
     * membuka sesi waktu solat yang sungguhan. Barisnya tetap memakai tabel dan
     * pengatur yang sama supaya catatan kehadiran serta foto bukti memakai
     * jalan yang sudah ada, tetapi sengaja tidak pernah dibuat maupun ditutup
     * mesin sesi otomatis dan tidak dihitung pada rekap mana pun.
     */
    public function testSession(): ?SolatSession
    {
        return SolatSession::query()
            ->test()
            ->whereIn('status', [SolatSession::StatusSedangDibuka, SolatSession::StatusTerjadwal])
            ->latest('opened_at')
            ->first();
    }

    /**
     * Nyalakan mode uji coba absen wajah.
     *
     * @return array{ok: bool, message: string}
     */
    public function startTestSession(?User $by = null): array
    {
        $test = $this->testSession();

        if ($test !== null && $test->isOpen()) {
            return ['ok' => false, 'message' => 'Mode uji coba sudah menyala. Kamera siap menerima percobaan absen.'];
        }

        if (($other = $this->activeSession()) !== null) {
            return [
                'ok' => false,
                'message' => 'Sesi '.$other->label().' masih dibuka. Tutup dulu sebelum menyalakan mode uji coba.',
            ];
        }

        $now = Carbon::now();

        if ($test === null) {
            $test = SolatSession::create([
                'prayer_name' => SolatSession::TestPrayerName,
                'date' => $now->toDateString(),
                'start_time' => $now->format('H:i'),
                'end_time' => $now->copy()->endOfDay()->format('H:i'),
                'status' => SolatSession::StatusBelumDibuka,
                'is_test' => true,
            ]);
        } else {
            $test->forceFill(['date' => $now->toDateString()])->save();
        }

        $this->markOpened($test, $by, $now);

        return [
            'ok' => true,
            'message' => 'Mode uji coba menyala. Absen yang masuk tidak dihitung pada rekap, dan orang yang sama bisa mencoba lagi setelah '
                .self::testCooldownMinutes().' menit.',
        ];
    }

    /**
     * Matikan mode uji coba tanpa menghapus catatannya.
     *
     * @return array{ok: bool, message: string}
     */
    public function stopTestSession(?User $by = null): array
    {
        $test = $this->testSession();

        if ($test === null || ! $test->isOpen()) {
            return ['ok' => false, 'message' => 'Mode uji coba belum menyala.'];
        }

        $total = $test->logs()->count();

        $this->markClosed($test, $by, Carbon::now());

        return [
            'ok' => true,
            'message' => 'Mode uji coba dimatikan dengan '.$total.' catatan percobaan. Catatannya masih bisa dilihat dan dihapus lewat tombol hapus sesi uji coba.',
        ];
    }

    /**
     * Hapus semua sesi uji coba beserta catatan dan foto buktinya.
     *
     * @return array{ok: bool, message: string}
     */
    public function deleteTestSession(?SolatProofService $proofs = null): array
    {
        $proofs ??= app(SolatProofService::class);

        $sessions = SolatSession::query()->test()->withCount('logs')->get();

        if ($sessions->isEmpty()) {
            return ['ok' => false, 'message' => 'Belum ada sesi uji coba yang bisa dihapus.'];
        }

        $total = (int) $sessions->sum('logs_count');

        $proofs->forgetForSessions($sessions->modelKeys());

        foreach ($sessions as $session) {
            $session->delete();
        }

        return [
            'ok' => true,
            'message' => 'Sesi uji coba dihapus bersama '.$total.' catatan percobaan dan foto buktinya.',
        ];
    }

    /**
     * Hapus satu sesi waktu solat beserta catatan kehadirannya.
     *
     * @return array{ok: bool, message: string, deleted: int}
     */
    public function delete(SolatSession $session, ?SolatProofService $proofs = null): array
    {
        return $this->deleteRows(new Collection([$session]), $proofs ?? app(SolatProofService::class));
    }

    /**
     * Hapus sesi terpilih beserta catatan kehadirannya.
     *
     * @param  Collection<int, SolatSession>  $sessions
     * @return array{ok: bool, message: string, deleted: int}
     */
    public function deleteMany(Collection $sessions, ?SolatProofService $proofs = null): array
    {
        return $this->deleteRows($sessions, $proofs ?? app(SolatProofService::class));
    }

    /**
     * Hapus semua sesi pada satu tanggal beserta catatan kehadirannya.
     *
     * Dipakai saat petugas salah membuat sesi untuk tanggal yang keliru.
     * Daftar waktu solat tidak pernah ikut tersentuh.
     *
     * @return array{ok: bool, message: string, deleted: int}
     */
    public function deleteForDate(CarbonInterface|string $date, ?SolatProofService $proofs = null): array
    {
        $day = Carbon::parse($date)->toDateString();
        $sessions = SolatSession::query()->real()->forDate($day)->get();

        if ($sessions->isEmpty()) {
            return [
                'ok' => false,
                'message' => 'Tidak ada sesi pada tanggal '.Carbon::parse($day)->translatedFormat('d F Y').'.',
                'deleted' => 0,
            ];
        }

        return $this->deleteRows($sessions, $proofs ?? app(SolatProofService::class));
    }

    /**
     * Jalan yang sama untuk ketiga cara penghapusan.
     *
     * Dua hal yang sengaja dijaga:
     *
     * 1. Sesi uji coba tidak pernah lewat sini, karena cara penghapusannya
     *    sendiri ada di deleteTestSession().
     * 2. Berkas foto bukti dibuang lebih dulu, sebab catatan kehadirannya ikut
     *    terhapus bersama sesinya sehingga berkasnya tidak akan bisa ditemukan
     *    lagi dan hanya jadi sampah di penyimpanan. Sesi yang sedang dibuka
     *    ikut dihapus agar tidak menyisakan status sesi terbuka setelah dihapus.
     *
     * @param  Collection<int, SolatSession>  $sessions
     * @return array{ok: bool, message: string, deleted: int}
     */
    private function deleteRows(Collection $sessions, SolatProofService $proofs): array
    {
        $rows = $sessions
            ->reject(fn (SolatSession $session): bool => $session->isTest())
            ->values();

        if ($rows->isEmpty()) {
            return ['ok' => false, 'message' => 'Tidak ada sesi yang bisa dihapus.', 'deleted' => 0];
        }

        $proofs->forgetForSessions($rows->modelKeys());

        foreach ($rows as $session) {
            $session->delete();
        }

        $deleted = $rows->count();

        $message = match (true) {
            $deleted === 1 => 'Sesi '.$rows->first()->label().' dihapus beserta catatan kehadirannya.',
            default => $deleted.' sesi dihapus beserta catatan kehadirannya.',
        };

        return ['ok' => true, 'message' => $message, 'deleted' => $deleted];
    }
}
