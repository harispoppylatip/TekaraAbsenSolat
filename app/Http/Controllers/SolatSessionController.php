<?php

namespace App\Http\Controllers;

use App\Models\PrayerTime;
use App\Models\SolatAttendanceLog;
use App\Models\SolatSession;
use App\Models\User;
use App\Services\SolatSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class SolatSessionController extends Controller
{
    public function index(Request $request, SolatSessionService $service): View
    {
        $date = $this->resolveDate($request->query('date'));

        $sessions = SolatSession::query()
            ->forDate($date)
            ->with(['opener', 'closer'])
            ->withCount([
                'logs',
                'logs as late_count' => fn ($query) => $query->where('status', SolatAttendanceLog::StatusTerlambat),
            ])
            ->orderBy('start_time')
            ->get();

        $selected = null;

        if ($request->filled('session')) {
            $selected = $sessions->firstWhere('id', (int) $request->query('session'));
        }

        if ($selected === null) {
            $selected = $sessions->firstWhere('status', SolatSession::StatusSedangDibuka) ?? $sessions->first();
        }

        return view('sessions.index', [
            'date' => $date,
            'sessions' => $sessions,
            'selected' => $selected,
            'activeSession' => $service->activeSession(),
            'logs' => $selected?->logs()->with('user')->orderByDesc('scanned_at')->get() ?? collect(),
            'memberTotal' => User::query()->where('role', User::RoleMurid)->count(),
            'prayerOptions' => PrayerTime::query()->ordered()->get(),
        ]);
    }

    public function generate(Request $request, SolatSessionService $service): RedirectResponse
    {
        $date = $this->resolveDate($request->input('date'));
        $result = $service->generateForDate($date);

        if ($result['total'] === 0) {
            return redirect()
                ->route('sessions.index', ['date' => $date->toDateString()])
                ->with('error', 'Belum ada waktu solat yang aktif. Isi dulu daftar waktu solat.');
        }

        $message = $result['created'] > 0
            ? $result['created'].' sesi dibuat untuk '.$date->translatedFormat('d M Y').'.'
            : 'Sesi untuk '.$date->translatedFormat('d M Y').' sudah lengkap, tidak ada yang ditambahkan.';

        return redirect()
            ->route('sessions.index', ['date' => $date->toDateString()])
            ->with('status', $message.' Total '.$result['total'].' waktu solat aktif.');
    }

    public function open(Request $request, SolatSession $solatSession, SolatSessionService $service): RedirectResponse
    {
        $result = $service->open($solatSession, $request->user());

        return redirect()
            ->route('sessions.index', ['date' => $solatSession->date?->toDateString(), 'session' => $solatSession->id])
            ->with($result['ok'] ? 'status' : 'error', $result['message']);
    }

    public function close(Request $request, SolatSession $solatSession, SolatSessionService $service): RedirectResponse
    {
        $result = $service->close($solatSession, $request->user());

        return redirect()
            ->route('sessions.index', ['date' => $solatSession->date?->toDateString(), 'session' => $solatSession->id])
            ->with($result['ok'] ? 'status' : 'error', $result['message']);
    }

    private function resolveDate(mixed $value): Carbon
    {
        if (filled($value)) {
            try {
                return Carbon::parse((string) $value)->startOfDay();
            } catch (\Throwable) {
                // Tanggal tidak terbaca, jatuh ke hari ini.
            }
        }

        return Carbon::today();
    }
}
