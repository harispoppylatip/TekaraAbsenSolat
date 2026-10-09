<?php

namespace App\Services;

use App\Models\PrayerTime;
use App\Models\SolatSession;
use App\Models\User;
use Carbon\CarbonInterface;
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
    /** Menit sebelum waktu mulai saat petugas sudah boleh membuka sesi. */
    public const EarlyOpenMinutes = 10;

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

        $session->forceFill([
            'status' => SolatSession::StatusSedangDibuka,
            'opened_at' => Carbon::now(),
            'opened_by' => $by?->id,
            'closed_at' => null,
            'closed_by' => null,
        ])->save();

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

        $session->forceFill([
            'status' => SolatSession::StatusSudahDitutup,
            'closed_at' => Carbon::now(),
            'closed_by' => $by?->id,
        ])->save();

        $total = $session->logs()->count();

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

        if ($now->lessThan($start->copy()->subMinutes(self::EarlyOpenMinutes))) {
            return 'Sesi '.$session->prayerLabel().' baru bisa dibuka '.self::EarlyOpenMinutes.' menit sebelum pukul '
                .$session->shortTime($session->start_time).'.';
        }

        if ($now->greaterThan($end)) {
            return 'Waktu '.$session->prayerLabel().' sudah lewat pukul '.$session->shortTime($session->end_time)
                .'. Hubungi admin bila sesi ini perlu dibuka.';
        }

        return null;
    }

    /**
     * Gabungkan tanggal sesi dengan salah satu kolom jamnya menjadi satu titik
     * waktu penuh.
     */
    public function moment(SolatSession $session, string $column): Carbon
    {
        return Carbon::parse($session->date?->toDateString().' '.$session->{$column});
    }

    public function canOpen(SolatSession $session, ?User $by = null): bool
    {
        return $this->openGuard($session, $by) === null && ! $session->isOpen();
    }
}
