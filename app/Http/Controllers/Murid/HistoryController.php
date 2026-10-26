<?php

namespace App\Http\Controllers\Murid;

use App\Http\Controllers\Controller;
use App\Models\PrayerTime;
use App\Models\SolatAttendanceLog;
use App\Services\SolatReportService;
use App\Services\SolatSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class HistoryController extends Controller
{
    public function __invoke(Request $request, SolatReportService $reports): View
    {
        $user = $request->user();

        $from = $this->parse($request->query('from')) ?? Carbon::today()->subDays(29);
        $to = $this->parse($request->query('to')) ?? Carbon::today();

        if ($to->lessThan($from)) {
            [$from, $to] = [$to, $from];
        }

        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();

        $totals = SolatAttendanceLog::query()
            ->where('user_id', $user->id)
            ->whereHas('session', fn ($query) => $query->real())
            ->whereBetween('scanned_at', [$start, $end])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $perPrayer = SolatAttendanceLog::query()
            ->join('solat_sessions', 'solat_sessions.id', '=', 'solat_attendance_logs.solat_session_id')
            ->where('solat_sessions.is_test', false)
            ->where('solat_attendance_logs.user_id', $user->id)
            ->whereBetween('solat_attendance_logs.scanned_at', [$start, $end])
            ->selectRaw('solat_sessions.prayer_name as prayer_name')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN solat_attendance_logs.status = 'terlambat' THEN 1 ELSE 0 END) as late_total")
            ->groupBy('solat_sessions.prayer_name')
            ->get()
            ->map(function ($row) {
                $prayer = PrayerTime::query()->where('prayer_name', $row->prayer_name)->first();

                return [
                    'prayer_name' => $row->prayer_name,
                    'label' => $prayer?->label ?? ucfirst((string) $row->prayer_name),
                    'total' => (int) $row->total,
                    'late' => (int) $row->late_total,
                ];
            })
            ->sortBy(fn (array $row): int => (int) (PrayerTime::query()
                ->where('prayer_name', $row['prayer_name'])
                ->value('sort_order') ?? 99))
            ->values();

        $activeSession = app(SolatSessionService::class)->activeSession();
        $dailyHistory = $reports->memberDailyHistory($user, $from, $to, 10);

        return view('murid.riwayat', [
            'user' => $user,
            'from' => $from,
            'to' => $to,
            'present' => (int) ($totals[SolatAttendanceLog::StatusHadir] ?? 0),
            'late' => (int) ($totals[SolatAttendanceLog::StatusTerlambat] ?? 0),
            'activeSession' => $activeSession,
            'openedSessions' => $activeSession ? 1 : 0,
            'perPrayer' => $perPrayer,
            'dailyHistory' => $dailyHistory,
            'logs' => $dailyHistory,
            'faceCount' => $user->faceEmbeddings()->count(),
            'isFaceEnrolled' => $user->hasCompletedEnrollment(),
            'needsEnrollment' => ! $user->isExemptFromEnrollment() && (! $user->hasCompletedEnrollment() || $user->faceEmbeddings()->count() < 5),
        ]);
    }

    private function parse(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
