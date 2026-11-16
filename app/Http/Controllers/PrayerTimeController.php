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
        PrayerTime::ensureStandardPrayers();
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

    public function updateActivePrayers(Request $request, SolatSessionService $service): RedirectResponse
    {
        PrayerTime::ensureStandardPrayers();

        $data = $request->validate([
            'prayers' => ['nullable', 'array'],
            'prayers.*' => ['string', 'max:30'],
        ]);

        $selected = array_map(
            static fn ($val): string => mb_strtolower(trim((string) $val)),
            $data['prayers'] ?? []
        );

        $prayers = PrayerTime::query()->ordered()->get();
        $activeLabels = [];

        foreach ($prayers as $prayer) {
            $isActive = in_array(mb_strtolower($prayer->prayer_name), $selected, true);
            $prayer->update(['is_active' => $isActive]);

            if ($isActive) {
                $activeLabels[] = $prayer->label;
            }
        }

        $this->syncSessionsWithActivePrayers($service);

        $summaryText = $activeLabels !== []
            ? implode(', ', $activeLabels)
            : 'Tidak ada waktu solat yang diaktifkan';

        return back()->with('status', 'Pilihan waktu solat yang dipakai berhasil diperbarui ('.$summaryText.').');
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

    public function update(PrayerTimeRequest $request, PrayerTime $prayerTime, SolatSessionService $service): RedirectResponse
    {
        $validated = $request->validated();

        $standardCodes = array_column(PrayerTime::StandardPrayers, 'prayer_name');
        if (in_array($prayerTime->prayer_name, $standardCodes, true)) {
            $validated['prayer_name'] = $prayerTime->prayer_name;
        }

        $prayerTime->update($validated);

        SolatSession::query()
            ->real()
            ->forDate(today())
            ->where('prayer_name', $prayerTime->prayer_name)
            ->where('status', SolatSession::StatusBelumDibuka)
            ->whereNull('opened_by')
            ->whereNull('closed_by')
            ->update([
                'start_time' => $prayerTime->start_time,
                'end_time' => $prayerTime->end_time,
            ]);

        $this->syncSessionsWithActivePrayers($service);

        return back()->with('status', 'Waktu solat '.$prayerTime->label.' diperbarui.');
    }

    public function destroy(PrayerTime $prayerTime): RedirectResponse
    {
        $standardCodes = array_column(PrayerTime::StandardPrayers, 'prayer_name');
        if (in_array($prayerTime->prayer_name, $standardCodes, true)) {
            return back()->with('error', 'Waktu solat '.$prayerTime->label.' tidak dapat dihapus. Gunakan cekbox untuk memilih waktu solat yang ingin dipakai.');
        }

        // Bersihkan sesi kosong yang belum memiliki catatan presensi untuk waktu sholat ini
        SolatSession::query()
            ->where('prayer_name', $prayerTime->prayer_name)
            ->whereDoesntHave('logs')
            ->delete();

        $label = $prayerTime->label;
        $prayerTime->delete();

        return back()->with('status', 'Waktu solat '.$label.' dihapus.');
    }

    /**
     * Bersihkan sesi kosong milik waktu solat yang dinonaktifkan dan buat sesi hari ini
     * untuk waktu solat yang baru diaktifkan.
     */
    private function syncSessionsWithActivePrayers(SolatSessionService $service): void
    {
        $inactiveNames = PrayerTime::query()
            ->where('is_active', false)
            ->pluck('prayer_name')
            ->all();

        if ($inactiveNames !== []) {
            SolatSession::query()
                ->real()
                ->whereIn('prayer_name', $inactiveNames)
                ->where('status', SolatSession::StatusBelumDibuka)
                ->whereDoesntHave('logs')
                ->delete();
        }

        if ($service->isActiveDay(today())) {
            $service->generateForDate(today());
        }
    }
}
