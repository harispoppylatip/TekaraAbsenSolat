<?php

namespace App\Http\Controllers;

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
            ->forDate($today)
            ->withCount('logs')
            ->orderBy('start_time')
            ->get();

        $stats = [
            'anggota' => User::query()->count(),
            'berwajah' => User::query()->has('faceEmbeddings')->count(),
            'sesi' => $todaySessions->count(),
            'absen' => SolatAttendanceLog::query()
                ->whereHas('session', fn ($query) => $query->forDate($today))
                ->count(),
        ];

        $recent = SolatAttendanceLog::query()
            ->with(['user', 'session'])
            ->latest('scanned_at')
            ->limit(8)
            ->get();

        $week = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = $today->copy()->subDays($i);

            $week[] = [
                'label' => $day->translatedFormat('d M'),
                'total' => SolatAttendanceLog::query()
                    ->whereHas('session', fn ($query) => $query->forDate($day))
                    ->count(),
            ];
        }

        return view('dashboard', [
            'activeSession' => $sessions->activeSession(),
            'todaySessions' => $todaySessions,
            'stats' => $stats,
            'recent' => $recent,
            'week' => $week,
        ]);
    }
}
