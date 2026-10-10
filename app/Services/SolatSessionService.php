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
     */
    public function activeSession(): ?SolatSession
    {
        return SolatSession::query()->open()->latest('opened_at')->first();
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
}
