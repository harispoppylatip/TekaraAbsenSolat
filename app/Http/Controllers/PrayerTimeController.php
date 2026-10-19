<?php

namespace App\Http\Controllers;

use App\Http\Requests\PrayerTimeRequest;
use App\Models\PrayerTime;
use App\Models\SolatSession;
use App\Services\SolatSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrayerTimeController extends Controller
{
    public function index(SolatSessionService $service): View
    {
        return view('prayer-times.index', [
            'prayers' => PrayerTime::query()->ordered()->get(),
            'suggestions' => (array) config('solat.prayer_defaults'),
            'earlyOpenMinutes' => SolatSessionService::earlyOpenMinutes(),
            'dayOptions' => SolatSessionService::dayOptions(),
            'activeDays' => $service->activeDays(),
            'daysSummary' => $service->activeDaysSummary(),
            'sessionTotals' => SolatSession::query()
                ->selectRaw('prayer_name, COUNT(*) as total')
                ->groupBy('prayer_name')
                ->pluck('total', 'prayer_name'),
        ]);
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
        $used = SolatSession::query()->where('prayer_name', $prayerTime->prayer_name)->exists();

        if ($used) {
            return back()->with('error', 'Waktu solat '.$prayerTime->label.' tidak bisa dihapus karena sudah dipakai sesi absen. Nonaktifkan saja supaya tidak ikut dibuat lagi.');
        }

        $label = $prayerTime->label;
        $prayerTime->delete();

        return back()->with('status', 'Waktu solat '.$label.' dihapus.');
    }
}
