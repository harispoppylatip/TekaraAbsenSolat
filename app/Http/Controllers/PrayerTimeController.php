<?php

namespace App\Http\Controllers;

use App\Http\Requests\PrayerTimeRequest;
use App\Models\PrayerTime;
use App\Models\SolatSession;
use App\Services\KhgtPrayerSyncService;
use App\Services\SolatSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrayerTimeController extends Controller
{
    public function index(SolatSessionService $service, KhgtPrayerSyncService $khgt): View
    {
        $khgt->ensureSyncedToday();

        return view('prayer-times.index', [
            'prayers' => PrayerTime::query()->ordered()->get(),
            'suggestions' => (array) config('solat.prayer_defaults'),
            'earlyOpenMinutes' => SolatSessionService::earlyOpenMinutes(),
            'dayOptions' => SolatSessionService::dayOptions(),
            'activeDays' => $service->activeDays(),
            'daysSummary' => $service->activeDaysSummary(),
            'khgtConfig' => $khgt->config(),
            'sessionTotals' => SolatSession::query()
                ->selectRaw('prayer_name, COUNT(*) as total')
                ->groupBy('prayer_name')
                ->pluck('total', 'prayer_name'),
        ]);
    }

    public function syncKhgt(Request $request, KhgtPrayerSyncService $khgt): RedirectResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'window_minutes' => ['required', 'integer', 'between:10,180'],
            'auto_sync' => ['nullable', 'boolean'],
        ], [
            'latitude.required' => 'Garis lintang (Latitude) wajib diisi.',
            'latitude.numeric' => 'Garis lintang (Latitude) harus berupa angka koordinat.',
            'longitude.required' => 'Garis bujur (Longitude) wajib diisi.',
            'longitude.numeric' => 'Garis bujur (Longitude) harus berupa angka koordinat.',
            'window_minutes.required' => 'Lama waktu absen dibuka wajib diisi.',
            'window_minutes.integer' => 'Lama waktu absen dibuka harus berupa angka menit.',
            'window_minutes.between' => 'Lama waktu absen dibuka harus antara 10 sampai 180 menit.',
        ]);

        $result = $khgt->sync(
            (string) $data['latitude'],
            (string) $data['longitude'],
            (int) $data['window_minutes'],
            $request->boolean('auto_sync'),
        );

        if (! $result['ok']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('status', $result['message']);
    }

    public function store(PrayerTimeRequest $request, SolatSessionService $service): RedirectResponse
    {
        $prayer = PrayerTime::create(array_merge(
            $request->validated(),
            ['active_days' => $service->activeDays()],
        ));

        return back()->with('status', 'Waktu solat '.$prayer->label.' ditambahkan.');
    }

    public function updateDays(Request $request, SolatSessionService $service): RedirectResponse
    {
        $data = $request->validate([
            'days' => ['required', 'array', 'min:1'],
            'days.*' => ['integer', 'between:1,7'],
        ], [
            'days.required' => 'Pilih paling sedikit satu hari aktif absen dalam seminggu.',
            'days.array' => 'Format pilihan hari tidak valid.',
            'days.min' => 'Pilih paling sedikit satu hari aktif absen dalam seminggu.',
        ]);

        $service->updateActiveDays($data['days']);
        $summary = $service->activeDaysSummary();

        return back()->with('status', 'Hari absen sekolah diperbarui ('.$summary['summary'].').');
    }

    public function update(PrayerTimeRequest $request, PrayerTime $prayerTime): RedirectResponse
    {
        $prayerTime->update($request->validated());

        return back()->with('status', 'Waktu solat '.$prayerTime->label.' diperbarui.');
    }

    public function destroy(PrayerTime $prayerTime): RedirectResponse
    {
        // Bersihkan sesi kosong yang belum memiliki catatan presensi untuk waktu sholat ini
        SolatSession::query()
            ->where('prayer_name', $prayerTime->prayer_name)
            ->whereDoesntHave('logs')
            ->delete();

        $label = $prayerTime->label;
        $prayerTime->delete();

        return back()->with('status', 'Waktu solat '.$label.' dihapus.');
    }
}
