<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Fingerprint;
use App\Models\SolatAttendanceLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class FingerprintDataController extends Controller
{
    /**
     * Daftar pilihan posisi jari standar untuk perekaman sensor AS608.
     *
     * @var list<string>
     */
    public const FingerPositions = [
        'Telunjuk Kanan',
        'Telunjuk Kiri',
        'Jempol Kanan',
        'Jempol Kiri',
        'Jari Tengah Kanan',
        'Jari Tengah Kiri',
    ];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $filter = (string) $request->query('filter', 'terdaftar');

        if (! in_array($filter, ['terdaftar', 'belum', 'semua'], true)) {
            $filter = 'terdaftar';
        }

        $membersQuery = User::query()
            ->with(['fingerprints' => fn ($query) => $query->latest('id')])
            ->withCount('fingerprints')
            ->when($filter === 'terdaftar', fn ($query) => $query->has('fingerprints'))
            ->when($filter === 'belum', fn ($query) => $query->doesntHave('fingerprints'))
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.$search.'%';

                $query->where(function ($inner) use ($like) {
                    $inner->where('name', 'like', $like)
                        ->orWhere('identifier_number', 'like', $like)
                        ->orWhere('class_name', 'like', $like);
                });
            })
            ->orderByDesc('fingerprints_count')
            ->orderBy('name');

        $members = $membersQuery->paginate(15)->withQueryString();

        $allMembers = User::query()
            ->whereNotNull('identifier_number')
            ->where('identifier_number', '!=', '')
            ->withCount('fingerprints')
            ->orderBy('name')
            ->get(['id', 'name', 'identifier_number', 'class_name', 'role']);

        $activeDevices = Device::query()
            ->where('status', Device::StatusAktif)
            ->orderBy('name')
            ->get();

        $cameraDevices = array_values(array_filter([
            config('solat.device_id', 'kamera-wajah'),
            config('face.device_id', 'kamera-wajah'),
            'kamera-wajah',
        ]));

        $recentLogs = SolatAttendanceLog::query()
            ->with(['user', 'session'])
            ->whereNotNull('device_id')
            ->where('device_id', '!=', '')
            ->whereNotIn('device_id', $cameraDevices)
            ->whereNull('proof_photo_path')
            ->latest('scanned_at')
            ->limit(15)
            ->get();

        $todayFingerprintLogs = SolatAttendanceLog::query()
            ->whereNotNull('device_id')
            ->where('device_id', '!=', '')
            ->whereNotIn('device_id', $cameraDevices)
            ->whereNull('proof_photo_path')
            ->whereDate('scanned_at', Carbon::today())
            ->count();

        $stats = [
            'terdaftar' => User::query()->has('fingerprints')->count(),
            'total_anggota' => User::query()->count(),
            'template' => Fingerprint::query()->count(),
            'perangkat_aktif' => $activeDevices->count(),
            'absen_hari_ini' => $todayFingerprintLogs,
        ];

        return view('fingerprint.index', [
            'members' => $members,
            'allMembers' => $allMembers,
            'activeDevices' => $activeDevices,
            'recentLogs' => $recentLogs,
            'stats' => $stats,
            'search' => $search,
            'filter' => $filter,
            'fingerPositions' => self::FingerPositions,
            'pendingEnroll' => Cache::get('fingerprint.pending_enroll'),
            'apiMode' => (string) config('solat.fingerprint.mode', 'offline'),
            'apiUrlOffline' => (string) config('solat.fingerprint.url_offline', 'http://localhost:8000/api/fingerprint'),
            'apiUrlOnline' => (string) config('solat.fingerprint.url_online', 'https://absenmu.tekara.my.id/api/fingerprint'),
        ]);
    }

    /**
     * Kirim perintah rekam sidik jari (enroll) ke sensor ESP32 yang aktif.
     */
    public function enroll(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'finger_position' => ['nullable', 'string', 'max:30'],
            'device_id' => ['nullable', 'string', 'max:50'],
        ], [
            'user_id.required' => 'Pilih anggota yang akan didaftarkan sidik jarinya.',
            'user_id.exists' => 'Anggota yang dipilih tidak ditemukan.',
        ]);

        $member = User::findOrFail($data['user_id']);

        if (blank($member->identifier_number)) {
            return back()->with(
                'error',
                "Anggota {$member->name} belum memiliki nomor induk (NIS/NIP). Isi nomor induknya lebih dulu di halaman Data Anggota.",
            );
        }

        $fingerPosition = trim((string) ($data['finger_position'] ?? 'Telunjuk Kanan')) ?: 'Telunjuk Kanan';
        $targetDeviceId = trim((string) ($data['device_id'] ?? ''));

        $this->queueEnrollCommand($member, $fingerPosition, $targetDeviceId !== '' ? $targetDeviceId : null);

        return back()->with(
            'status',
            "Perintah rekam sidik jari ({$fingerPosition}) untuk {$member->name} ({$member->identifier_number}) telah dikirim ke sensor. Silakan tempelkan jari 2 kali pada sensor sidik jari dalam 2 menit.",
        );
    }

    /**
     * Hapus template sidik jari lama milik anggota lalu kirim perintah rekam ulang ke sensor.
     */
    public function replace(Request $request, User $member): RedirectResponse
    {
        if (blank($member->identifier_number)) {
            return back()->with(
                'error',
                "Anggota {$member->name} belum memiliki nomor induk.",
            );
        }

        $lastFinger = $member->fingerprints()->latest('id')->value('finger_position') ?: 'Telunjuk Kanan';
        $fingerPosition = trim((string) $request->input('finger_position', $lastFinger)) ?: 'Telunjuk Kanan';

        $member->fingerprints()->delete();

        $this->queueEnrollCommand($member, $fingerPosition, null);

        return back()->with(
            'status',
            "Template lama {$member->name} dihapus dan perintah rekam ulang ({$fingerPosition}) telah dikirim ke sensor. Silakan tempelkan jari pada alat.",
        );
    }

    /**
     * Batalkan perintah perekaman sidik jari yang sedang menunggu di antrean cache.
     */
    public function cancelEnroll(): RedirectResponse
    {
        $this->clearEnrollCommands();

        return back()->with(
            'status',
            'Perintah perekaman sidik jari berhasil dibatalkan.',
        );
    }

    /**
     * Cabut akses absen sidik jari seorang anggota (hapus seluruh template jarinya).
     */
    public function destroyMember(User $member): RedirectResponse
    {
        $deleted = $member->fingerprints()->delete();

        $pending = Cache::get('fingerprint.pending_enroll');
        if (is_array($pending) && ($pending['user_id'] ?? null) === $member->identifier_number) {
            $this->clearEnrollCommands();
        }

        return back()->with(
            'status',
            $deleted > 0
                ? "Data sidik jari {$member->name} berhasil dihapus. Anggota ini tidak lagi menggunakan mode absen sidik jari."
                : "Anggota {$member->name} belum memiliki data sidik jari.",
        );
    }

    private function queueEnrollCommand(User $member, string $fingerPosition, ?string $specificDeviceId): void
    {
        $ttl = max(30, (int) config('solat.fingerprint.command_ttl', 120));

        $payload = [
            'mode' => 'enroll',
            'user_id' => $member->identifier_number,
            'name' => $member->name,
            'finger_position' => $fingerPosition,
            'queued_at' => now()->format('H:i:s'),
        ];

        $this->clearEnrollCommands();

        if ($specificDeviceId !== null && $specificDeviceId !== '') {
            Cache::put('fingerprint.command.'.$specificDeviceId, $payload, $ttl);
        } else {
            $devices = Device::query()->where('status', Device::StatusAktif)->pluck('device_id');

            foreach ($devices as $deviceId) {
                Cache::put('fingerprint.command.'.$deviceId, $payload, $ttl);
            }

            Cache::put('fingerprint.command.any', $payload, $ttl);
        }

        Cache::put('fingerprint.pending_enroll', $payload + [
            'member_id' => $member->id,
            'device_id' => $specificDeviceId ?: 'Semua sensor aktif',
            'expires_at' => now()->addSeconds($ttl)->format('H:i:s'),
        ], $ttl);
    }

    private function clearEnrollCommands(): void
    {
        Cache::forget('fingerprint.pending_enroll');
        Cache::forget('fingerprint.command.any');

        $deviceIds = Device::query()->pluck('device_id');
        foreach ($deviceIds as $deviceId) {
            Cache::forget('fingerprint.command.'.$deviceId);
        }
    }
}
