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
            ->real()
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

        $testSession = $service->testSession();

        return view('sessions.index', [
            'date' => $date,
            'isActiveDay' => $service->isActiveDay($date),
            'daysSummary' => $service->activeDaysSummary(),
            'sessions' => $sessions,
            'selected' => $selected,
            'activeSession' => $service->activeSession(),
            'logs' => $selected?->logs()->with('user')->orderByDesc('scanned_at')->get() ?? collect(),
            'memberTotal' => User::query()->where('role', User::RoleMurid)->count(),
            'prayerOptions' => PrayerTime::query()->ordered()->get(),
            'testSession' => $testSession,
            'testCount' => $testSession === null ? 0 : $testSession->logs()->count(),
            'testLogs' => $testSession === null
                ? collect()
                : $testSession->logs()->with('user')->orderByDesc('scanned_at')->limit(15)->get(),
            'testCooldownMinutes' => SolatSessionService::testCooldownMinutes(),
        ]);
    }

    public function generate(Request $request, SolatSessionService $service): RedirectResponse
    {
        $date = $this->resolveDate($request->input('date'));

        if (! $service->isActiveDay($date) && ! $request->user()?->isAdmin()) {
            return redirect()
                ->route('sessions.index', ['date' => $date->toDateString()])
                ->with('error', 'Hari '.$date->translatedFormat('l').' termasuk hari libur absen. Hanya admin yang bisa membuat sesi pada hari libur.');
        }

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

    /**
     * Hapus satu sesi beserta catatan kehadirannya.
     *
     * Sesi yang sedang dibuka ditolak oleh layanan supaya kamera yang sedang
     * menerima absen tidak kehilangan sesinya tanpa ada yang menyadari.
     */
    public function destroy(Request $request, SolatSession $solatSession, SolatSessionService $service): RedirectResponse
    {
        if ($solatSession->isTest()) {
            return redirect()
                ->route('sessions.index')
                ->with('error', 'Sesi uji coba dihapus dari panel mode uji coba, bukan dari daftar sesi.');
        }

        $date = $solatSession->date?->toDateString();
        $result = $service->delete($solatSession);

        return redirect()
            ->route('sessions.index', ['date' => $date])
            ->with($result['ok'] ? 'status' : 'error', $result['message']);
    }

    /**
     * Hapus sesi yang dicentang petugas.
     */
    public function destroySelected(Request $request, SolatSessionService $service): RedirectResponse
    {
        $data = $request->validate([
            'sessions' => ['required', 'array'],
            'sessions.*' => ['integer'],
            'date' => ['nullable', 'date'],
        ], [
            'sessions.required' => 'Centang dulu sesi yang ingin dihapus.',
        ]);

        $sessions = SolatSession::query()
            ->real()
            ->whereIn('id', $data['sessions'])
            ->get();

        $result = $service->deleteMany($sessions);

        return redirect()
            ->route('sessions.index', array_filter(['date' => $data['date'] ?? null]))
            ->with($result['ok'] ? 'status' : 'error', $result['message']);
    }

    /**
     * Hapus semua sesi pada satu tanggal.
     */
    public function destroyDay(Request $request, SolatSessionService $service): RedirectResponse
    {
        $data = $request->validate(['date' => ['required', 'date']]);
        $date = Carbon::parse($data['date'])->startOfDay();
        $result = $service->deleteForDate($date);

        return redirect()
            ->route('sessions.index', ['date' => $date->toDateString()])
            ->with($result['ok'] ? 'status' : 'error', $result['message']);
    }

    /**
     * Nyalakan mode uji coba absen wajah.
     */
    public function startTest(Request $request, SolatSessionService $service): RedirectResponse
    {
        $result = $service->startTestSession($request->user());

        return redirect()
            ->route('sessions.index')
            ->with($result['ok'] ? 'status' : 'error', $result['message']);
    }

    /**
     * Matikan mode uji coba tanpa menghapus catatannya.
     */
    public function stopTest(Request $request, SolatSessionService $service): RedirectResponse
    {
        $result = $service->stopTestSession($request->user());

        return redirect()
            ->route('sessions.index')
            ->with($result['ok'] ? 'status' : 'error', $result['message']);
    }

    /**
     * Hapus sesi uji coba bersama catatan percobaan dan foto buktinya.
     */
    public function destroyTest(SolatSessionService $service): RedirectResponse
    {
        $result = $service->deleteTestSession();

        return redirect()
            ->route('sessions.index')
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
