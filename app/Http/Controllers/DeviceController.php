<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\SolatAttendanceLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public function index(): View
    {
        // Bersihkan baris kamera bawaan lama bila masih tersimpan di tabel perangkat
        Device::query()
            ->where(function ($q) {
                $q->whereIn('device_id', Device::builtInIds())
                    ->orWhere('device_id', 'like', 'kamera-%')
                    ->orWhere('device_id', 'like', 'cctv-%');
            })
            ->delete();

        return view('devices.index', [
            'pending' => Device::query()
                ->hardware()
                ->where('status', Device::StatusBelumDidaftarkan)
                ->orderByDesc('last_seen_at')
                ->get(),
            'registered' => Device::query()
                ->hardware()
                ->whereIn('status', [Device::StatusAktif, Device::StatusNonaktif])
                ->orderBy('name')
                ->get(),
            'types' => Device::types(),
            'stats' => [
                'total' => Device::query()->hardware()->count(),
                'aktif' => Device::query()->hardware()->where('status', Device::StatusAktif)->count(),
                'menunggu' => Device::query()->hardware()->where('status', Device::StatusBelumDidaftarkan)->count(),
                'absen' => SolatAttendanceLog::query()
                    ->whereNotNull('device_id')
                    ->whereNotIn('device_id', Device::builtInIds())
                    ->where('device_id', 'not like', 'kamera-%')
                    ->where('device_id', 'not like', 'cctv-%')
                    ->count(),
            ],
        ]);
    }

    public function register(Device $device): RedirectResponse
    {
        if ($device->isRegistered()) {
            return back()->with('error', 'Perangkat '.$device->name.' sudah didaftarkan.');
        }

        $device->forceFill(['status' => Device::StatusNonaktif])->save();

        return back()->with('status', 'Perangkat '.$device->name.' berhasil didaftarkan. Nyalakan setelah penempatannya benar.');
    }

    public function update(Request $request, Device $device): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', 'string', Rule::in(array_keys(Device::types()))],
            'location' => ['nullable', 'string', 'max:80'],
        ], [
            'name.required' => 'Nama perangkat wajib diisi.',
            'type.required' => 'Jenis perangkat wajib dipilih.',
        ]);

        $device->update($data);

        return back()->with('status', 'Data perangkat '.$device->name.' diperbarui. Keadaan nyala atau matinya tidak ikut berubah.');
    }

    public function status(Request $request, Device $device): RedirectResponse
    {
        $active = $request->boolean('active');

        if (! $device->isRegistered()) {
            return back()->with('error', 'Perangkat '.$device->name.' belum didaftarkan, jadi belum bisa dinyalakan.');
        }

        $device->forceFill(['status' => $active ? Device::StatusAktif : Device::StatusNonaktif])->save();

        return back()->with('status', $active
            ? 'Perangkat '.$device->name.' dinyalakan. Absen dari perangkat ini sudah diterima.'
            : 'Perangkat '.$device->name.' dimatikan. Absen dari perangkat ini ditolak sampai dinyalakan lagi.');
    }

    public function destroy(Device $device): RedirectResponse
    {
        $name = $device->name;
        $device->delete();

        return back()->with('status', 'Perangkat '.$name.' dihapus. Riwayat absen yang sudah tercatat tetap tersimpan.');
    }
}
