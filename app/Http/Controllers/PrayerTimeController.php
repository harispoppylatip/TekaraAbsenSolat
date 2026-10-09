<?php

namespace App\Http\Controllers;

use App\Http\Requests\PrayerTimeRequest;
use App\Models\PrayerTime;
use App\Models\SolatSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PrayerTimeController extends Controller
{
    public function index(): View
    {
        return view('prayer-times.index', [
            'prayers' => PrayerTime::query()->ordered()->get(),
            'suggestions' => (array) config('solat.prayer_defaults'),
            'sessionTotals' => SolatSession::query()
                ->selectRaw('prayer_name, COUNT(*) as total')
                ->groupBy('prayer_name')
                ->pluck('total', 'prayer_name'),
        ]);
    }

    public function store(PrayerTimeRequest $request): RedirectResponse
    {
        $prayer = PrayerTime::create($request->validated());

        return back()->with('status', 'Waktu solat '.$prayer->label.' ditambahkan.');
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
