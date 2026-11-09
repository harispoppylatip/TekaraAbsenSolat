<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\PrayerTime;
use App\Models\SolatAttendanceLog;
use App\Models\SolatSession;
use App\Models\User;
use App\Services\SolatSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(SolatSessionService $sessions): View
    {
        $today = now();
        $prayerTimes = PrayerTime::query()->active()->ordered()->get();
        $todaySessions = SolatSession::query()
            ->real()
            ->forDate($today)
            ->get()
            ->keyBy('prayer_name');

        return view('auth.login', [
            'prayerTimes' => $prayerTimes,
            'todaySessions' => $todaySessions,
            'activeSession' => $sessions->activeSession(),
            'daysSummary' => $sessions->activeDaysSummary(),
            'recentItems' => collect($this->recentLogsPayload()),
            'serverNowIso' => $today->toIso8601String(),
            'todayLabel' => $today->translatedFormat('l, d F Y'),
        ]);
    }

    /**
     * Data real-time untuk halaman masuk (15 absen terakhir & status sesi aktif).
     */
    public function liveData(SolatSessionService $sessions): JsonResponse
    {
        if (session()->isStarted()) {
            session()->save();
        }

        $today = now();
        $activeSession = $sessions->activeSession();
        $closedPrayers = SolatSession::query()
            ->real()
            ->forDate($today)
            ->where('status', SolatSession::StatusSudahDitutup)
            ->pluck('prayer_name')
            ->values()
            ->all();

        return response()->json([
            'server_now_iso' => $today->toIso8601String(),
            'today_label' => $today->translatedFormat('l, d F Y'),
            'active_prayer' => $activeSession?->prayer_name,
            'closed_prayers' => $closedPrayers,
            'recent_logs' => $this->recentLogsPayload(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentLogsPayload(): array
    {
        return SolatAttendanceLog::query()
            ->with(['user:id,name,role,class_name', 'session:id,prayer_name,date,is_test'])
            ->whereHas('session', fn ($q) => $q->where('is_test', false))
            ->latest('scanned_at')
            ->limit(15)
            ->get()
            ->map(function (SolatAttendanceLog $log): array {
                $name = (string) ($log->user?->name ?? 'Jamaah');
                $words = preg_split('/\s+/', trim($name)) ?: [];
                $initials = mb_strtoupper(
                    mb_substr($words[0] ?? 'J', 0, 1)
                    .(isset($words[1]) ? mb_substr($words[1], 0, 1) : '')
                );
                $group = $log->user?->class_name ?: ($log->user ? $log->user->roleLabel() : 'Jamaah');
                $prayerLabel = $log->session?->prayerLabel() ?? 'Sholat';
                $scannedAt = $log->scanned_at;
                $dateLabel = $scannedAt
                    ? ($scannedAt->isToday() ? 'Hari ini' : $scannedAt->translatedFormat('d M'))
                    : '-';

                return [
                    'id' => (int) $log->id,
                    'name' => $name,
                    'initials' => $initials,
                    'group' => (string) $group,
                    'prayer' => (string) $prayerLabel,
                    'time' => $scannedAt ? $scannedAt->format('H:i') : '--:--',
                    'date_label' => (string) $dateLabel,
                    'status' => (string) $log->statusLabel(),
                    'is_late' => $log->status === SolatAttendanceLog::StatusTerlambat,
                    'method' => $log->isFingerprint() ? 'Sidik Jari' : 'Kamera Wajah',
                ];
            })
            ->values()
            ->all();
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credential = $request->loginCredential();
        $password = (string) $request->input('password');

        $user = User::query()
            ->where(function ($query) use ($credential) {
                $query->whereRaw('LOWER(email) = ?', [mb_strtolower($credential)])
                    ->orWhere('identifier_number', $credential)
                    ->orWhere('email', $credential);
            })
            ->first();

        if (! $user || ! Hash::check($password, (string) $user->password)) {
            $errorKey = $request->filled('login') ? 'login' : 'email';

            return back()
                ->withInput($request->only('login', 'email'))
                ->withErrors([$errorKey => 'NISN atau Email atau sandi tidak cocok.']);
        }

        Auth::login($user, $user->isKiosk() ? true : $request->boolean('remember'));

        $request->session()->regenerate();
        $request->session()->forget('error');

        $user->forceFill(['last_login_at' => now()])->save();

        $intended = (string) $request->session()->get('url.intended');

        if ($intended !== '') {
            if ($user->isKiosk()) {
                if (! str_contains($intended, 'layar')) {
                    $request->session()->forget('url.intended');
                }
            } elseif (str_contains($intended, 'absen-wajah') && ! $user->isAdmin() && ! $user->isCameraOperator()) {
                $request->session()->forget('url.intended');
            } elseif ($user->isMurid()) {
                $isAllowed = false;
                foreach (['riwayat', 'pendaftaran-wajah', 'ganti-sandi', 'profil'] as $path) {
                    if (str_contains($intended, $path)) {
                        $isAllowed = true;
                        break;
                    }
                }
                if (! $isAllowed) {
                    $request->session()->forget('url.intended');
                }
            }
        }

        return redirect()
            ->intended(route($user->homeRoute()))
            ->with('status', 'Selamat datang, '.$user->name.'.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('status', 'Anda sudah keluar dari aplikasi.');
    }
}
