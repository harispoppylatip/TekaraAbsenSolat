<?php

namespace App\Http\Controllers;

use App\Models\PrayerTime;
use App\Models\SolatAttendanceLog;
use App\Models\SolatSession;
use App\Models\User;
use App\Services\SolatSessionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

        $todayHadir = (int) $todaySessions->sum('hadir_count');
        $todayTerlambat = (int) $todaySessions->sum('terlambat_count');

        $userCounts = User::query()
            ->where('role', '!=', User::RoleKiosk)
            ->selectRaw('COUNT(*) as total_anggota, SUM(CASE WHEN role = ? THEN 1 ELSE 0 END) as total_murid', [User::RoleMurid])
            ->first();

        $stats = [
            'anggota' => (int) ($userCounts->total_anggota ?? 0),
            'murid' => (int) ($userCounts->total_murid ?? 0),
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

        // Hitung rekap 7 hari terakhir dalam 1 kueri agregasi (menggantikan 14 kueri perulangan)
        $weekRows = DB::table('solat_attendance_logs')
            ->join('solat_sessions', 'solat_sessions.id', '=', 'solat_attendance_logs.solat_session_id')
            ->where('solat_sessions.is_test', false)
            ->where('solat_sessions.prayer_name', '!=', SolatSession::TestPrayerName)
            ->whereDate('solat_sessions.date', '>=', $sevenDaysAgo->toDateString())
            ->whereDate('solat_sessions.date', '<=', $today->toDateString())
            ->selectRaw('DATE(solat_sessions.date) as day_date, solat_attendance_logs.status, COUNT(*) as total')
            ->groupByRaw('DATE(solat_sessions.date), solat_attendance_logs.status')
            ->get();

        $weekMap = [];
        foreach ($weekRows as $row) {
            $d = substr((string) $row->day_date, 0, 10);
            $weekMap[$d][$row->status] = (int) $row->total;
        }

        for ($i = 6; $i >= 0; $i--) {
            $day = $today->copy()->subDays($i);
            $dateKey = $day->toDateString();

            $hadir = $weekMap[$dateKey][SolatAttendanceLog::StatusHadir] ?? 0;
            $terlambat = $weekMap[$dateKey][SolatAttendanceLog::StatusTerlambat] ?? 0;

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

        // Statistik per waktu solat dalam 1 kueri agregasi (menggantikan 10 kueri perulangan)
        $prayerTimes = PrayerTime::query()->active()->orderBy('sort_order')->get();
        $prayerRows = DB::table('solat_attendance_logs')
            ->join('solat_sessions', 'solat_sessions.id', '=', 'solat_attendance_logs.solat_session_id')
            ->where('solat_sessions.is_test', false)
            ->where('solat_sessions.prayer_name', '!=', SolatSession::TestPrayerName)
            ->selectRaw('solat_sessions.prayer_name, solat_attendance_logs.status, COUNT(*) as total')
            ->groupBy('solat_sessions.prayer_name', 'solat_attendance_logs.status')
            ->get();

        $prayerMap = [];
        $allRealLogsTotal = 0;
        foreach ($prayerRows as $row) {
            $prayerMap[$row->prayer_name][$row->status] = (int) $row->total;
            $allRealLogsTotal += (int) $row->total;
        }

        $prayerStats = [];
        foreach ($prayerTimes as $pt) {
            $hadirCount = $prayerMap[$pt->prayer_name][SolatAttendanceLog::StatusHadir] ?? 0;
            $terlambatCount = $prayerMap[$pt->prayer_name][SolatAttendanceLog::StatusTerlambat] ?? 0;

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
