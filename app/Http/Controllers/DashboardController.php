<?php

namespace App\Http\Controllers;

use App\Models\PrayerTime;
use App\Models\SolatAttendanceLog;
use App\Models\SolatSession;
use App\Models\User;
use App\Services\SolatSessionService;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(SolatSessionService $sessions): View
    {
        $today = Carbon::today();

        $todaySessions = SolatSession::query()
            ->real()
            ->forDate($today)
            ->withCount([
                'logs',
                'logs as hadir_count' => fn ($q) => $q->where('status', SolatAttendanceLog::StatusHadir),
                'logs as terlambat_count' => fn ($q) => $q->where('status', SolatAttendanceLog::StatusTerlambat),
            ])
            ->orderBy('start_time')
            ->get();

        $todayHadir = SolatAttendanceLog::query()
            ->whereHas('session', fn ($query) => $query->real()->forDate($today))
            ->where('status', SolatAttendanceLog::StatusHadir)
            ->count();

        $todayTerlambat = SolatAttendanceLog::query()
            ->whereHas('session', fn ($query) => $query->real()->forDate($today))
            ->where('status', SolatAttendanceLog::StatusTerlambat)
            ->count();

        $stats = [
            'anggota' => User::query()->where('role', '!=', User::RoleKiosk)->count(),
            'murid' => User::query()->where('role', User::RoleMurid)->count(),
            'berwajah' => User::query()->where('role', '!=', User::RoleKiosk)->has('faceEmbeddings')->count(),
            'bersidik_jari' => User::query()->where('role', '!=', User::RoleKiosk)->has('fingerprints')->count(),
            'sesi' => $todaySessions->count(),
            'absen' => $todayHadir + $todayTerlambat,
            'hadir_hari_ini' => $todayHadir,
            'terlambat_hari_ini' => $todayTerlambat,
        ];

        $recent = SolatAttendanceLog::query()
            ->with(['user', 'session'])
            ->whereHas('session', fn ($query) => $query->real())
            ->latest('scanned_at')
            ->limit(8)
            ->get();

        $week = [];
        $sevenDaysAgo = $today->copy()->subDays(6)->startOfDay();

        for ($i = 6; $i >= 0; $i--) {
            $day = $today->copy()->subDays($i);

            $hadir = SolatAttendanceLog::query()
                ->whereHas('session', fn ($query) => $query->real()->forDate($day))
                ->where('status', SolatAttendanceLog::StatusHadir)
                ->count();

            $terlambat = SolatAttendanceLog::query()
                ->whereHas('session', fn ($query) => $query->real()->forDate($day))
                ->where('status', SolatAttendanceLog::StatusTerlambat)
                ->count();

            $week[] = [
                'label' => $day->translatedFormat('d M'),
                'day_short' => $day->translatedFormat('D'),
                'full_date' => $day->translatedFormat('l, d M Y'),
                'is_today' => $i === 0,
                'hadir' => $hadir,
                'terlambat' => $terlambat,
                'total' => $hadir + $terlambat,
            ];
        }

        // Statistik per waktu solat (30 hari terakhir / keseluruhan sesi nyata)
        $prayerTimes = PrayerTime::query()->orderBy('sort_order')->get();
        $prayerStats = [];

        foreach ($prayerTimes as $pt) {
            $hadirCount = SolatAttendanceLog::query()
                ->whereHas('session', fn ($q) => $q->real()->where('prayer_name', $pt->prayer_name))
                ->where('status', SolatAttendanceLog::StatusHadir)
                ->count();

            $terlambatCount = SolatAttendanceLog::query()
                ->whereHas('session', fn ($q) => $q->real()->where('prayer_name', $pt->prayer_name))
                ->where('status', SolatAttendanceLog::StatusTerlambat)
                ->count();

            $prayerStats[] = [
                'name' => $pt->prayer_name,
                'label' => $pt->label,
                'window' => substr((string) $pt->start_time, 0, 5).'–'.substr((string) $pt->end_time, 0, 5),
                'hadir' => $hadirCount,
                'terlambat' => $terlambatCount,
                'total' => $hadirCount + $terlambatCount,
            ];
        }

        $cameraDevices = array_values(array_filter([
            config('solat.device_id', 'kamera-wajah'),
            config('face.device_id', 'kamera-wajah'),
            'kamera-wajah',
        ]));

        $fingerprintTotal = SolatAttendanceLog::query()
            ->whereHas('session', fn ($q) => $q->real())
            ->whereNull('proof_photo_path')
            ->whereNotNull('device_id')
            ->where('device_id', '!=', '')
            ->whereNotIn('device_id', $cameraDevices)
            ->where('device_id', 'not like', 'cctv-%')
            ->where('device_id', 'not like', 'kamera-%')
            ->count();

        $allRealLogsTotal = SolatAttendanceLog::query()
            ->whereHas('session', fn ($q) => $q->real())
            ->count();

        $faceTotal = max(0, $allRealLogsTotal - $fingerprintTotal);

        return view('dashboard', [
            'activeSession' => $sessions->activeSession(),
            'todaySessions' => $todaySessions,
            'stats' => $stats,
            'recent' => $recent,
            'week' => $week,
            'prayerStats' => $prayerStats,
            'methodStats' => [
                'face' => $faceTotal,
                'fingerprint' => $fingerprintTotal,
                'total' => $allRealLogsTotal,
            ],
        ]);
    }
}
