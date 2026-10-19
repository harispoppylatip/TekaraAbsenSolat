<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\GuardsDevice;
use App\Http\Controllers\Controller;
use App\Models\Fingerprint;
use App\Models\User;
use App\Services\DeviceRegistryService;
use App\Services\FingerprintRegistryService;
use App\Services\SolatAttendanceService;
use App\Services\SolatSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class FingerprintApiController extends Controller
{
    use GuardsDevice;

    public function __construct(
        private FingerprintRegistryService $fingerprints,
        private SolatAttendanceService $attendance,
        private SolatSessionService $sessions,
        private DeviceRegistryService $devices,
    ) {}

    /**
     * Simpan template sidik jari baru dari alat.
     */
    public function register(Request $request): JsonResponse
    {
        [$device, $refusal] = $this->guardDevice($request, $this->devices);

        if ($refusal !== null) {
            return $refusal;
        }

        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:50'],
            'user_id' => ['required', 'string', 'max:50', 'exists:users,identifier_number'],
            'template' => ['required', 'string', 'max:1000000'],
            'finger_position' => ['nullable', 'string', 'max:30'],
        ]);

        $user = User::where('identifier_number', $data['user_id'])->firstOrFail();

        $pending = Cache::get('fingerprint.pending_enroll');
        $fingerPosition = $data['finger_position']
            ?? (is_array($pending) && ($pending['user_id'] ?? null) === $user->identifier_number ? ($pending['finger_position'] ?? null) : null);

        $fingerprint = $this->fingerprints->store($user, $data['template'], $fingerPosition);

        Cache::forget('fingerprint.pending_enroll');
        Cache::forget('fingerprint.command.any');
        Cache::forget('fingerprint.command.'.$data['device_id']);

        return response()->json([
            'status' => 'success',
            'message' => 'Template sidik jari berhasil disimpan.',
            'data' => [
                'id' => $fingerprint->id,
                'user_id' => $data['user_id'],
                'finger_position' => $fingerprint->finger_position,
            ],
        ], 201);
    }

    /**
     * Ambil template sidik jari milik seorang anggota.
     */
    public function template(Request $request, string $userId): JsonResponse
    {
        [$device, $refusal] = $this->guardDevice($request, $this->devices);

        if ($refusal !== null) {
            return $refusal;
        }

        $fingerprint = Fingerprint::query()
            ->whereHas('user', fn ($query) => $query->where('identifier_number', $userId))
            ->latest('id')
            ->first();

        if (! $fingerprint) {
            return response()->json([
                'status' => 'error',
                'message' => 'Template sidik jari tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Template sidik jari ditemukan.',
            'data' => [
                'user_id' => $userId,
                'template' => $fingerprint->template,
            ],
        ]);
    }

    /**
     * Rekam kehadiran dari hasil pencocokan lokal alat sidik jari.
     */
    public function match(Request $request): JsonResponse
    {
        [$device, $refusal] = $this->guardDevice($request, $this->devices);

        if ($refusal !== null) {
            return $refusal;
        }

        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:50'],
            'user_id' => ['nullable', 'string', 'max:50', 'exists:users,identifier_number'],
            'matched' => ['required', 'boolean'],
        ]);

        $matched = (bool) $data['matched'];
        $user = ! empty($data['user_id'])
            ? User::where('identifier_number', $data['user_id'])->first()
            : null;

        if (! $matched || $user === null) {
            return response()->json([
                'ok' => false,
                'status' => 'no_match',
                'message' => 'Sidik jari tidak cocok.',
                'user_id' => $data['user_id'] ?? null,
                'name' => null,
                'attendance_id' => null,
                'attendance_recorded' => false,
                'attendance_type' => null,
                'attendance_label' => null,
                'display_message' => 'Tidak cocok',
                'display_detail' => 'Coba lagi',
            ]);
        }

        if (! $user->fingerprints()->exists()) {
            return response()->json([
                'ok' => false,
                'status' => 'not_enrolled',
                'message' => 'Anggota ini belum didaftarkan pada mode absen sidik jari.',
                'user_id' => $user->identifier_number,
                'name' => $user->name,
                'attendance_id' => null,
                'attendance_recorded' => false,
                'attendance_type' => null,
                'attendance_label' => null,
                'display_message' => 'Belum terdaftar',
                'display_detail' => 'Hubungi admin',
            ]);
        }

        $session = $this->sessions->activeSession();

        if ($session === null) {
            $test = $this->sessions->testSession();
            if ($test?->isOpen() === true) {
                $session = $test;
            }
        }

        if ($session === null) {
            return response()->json([
                'ok' => false,
                'status' => 'no_session',
                'message' => 'Belum ada sesi absen yang dibuka. Minta petugas membuka sesi lebih dulu.',
                'user_id' => $user->identifier_number,
                'name' => $user->name,
                'attendance_id' => null,
                'attendance_recorded' => false,
                'attendance_type' => null,
                'attendance_label' => null,
                'display_message' => 'Sesi belum buka',
                'display_detail' => 'Belum dibuka',
            ]);
        }

        $recordResult = $this->attendance->record(
            user: $user,
            session: $session,
            deviceId: $device?->device_id,
        );

        $isRecorded = (bool) ($recordResult['ok'] ?? false);
        $log = $recordResult['log'] ?? null;

        return response()->json([
            'ok' => $isRecorded,
            'status' => $recordResult['status'] ?? ($isRecorded ? 'matched' : 'rejected'),
            'message' => $recordResult['message'] ?? '',
            'user_id' => $user->identifier_number,
            'name' => $user->name,
            'attendance_id' => $log?->id,
            'attendance_recorded' => $isRecorded,
            'attendance_type' => $session->isTest() ? 'test' : 'solat',
            'attendance_label' => $log?->statusLabel() ?? $recordResult['label'] ?? '',
            'display_message' => $recordResult['display_message'] ?? $user->name,
            'display_detail' => $recordResult['display_detail'] ?? ($log?->statusLabel() ?? ''),
        ]);
    }

    /**
     * Perintah yang menunggu dieksekusi sensor (misal pendaftaran).
     */
    public function command(Request $request): JsonResponse
    {
        [$device, $refusal] = $this->guardDevice($request, $this->devices);

        if ($refusal !== null) {
            return $refusal;
        }

        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:50'],
        ]);

        $command = Cache::pull('fingerprint.command.'.$data['device_id'])
            ?? Cache::pull('fingerprint.command.any');

        return response()->json($command ?: ['mode' => 'verify']);
    }

    /**
     * Seluruh template sidik jari untuk cache memori ESP32.
     */
    public function templates(Request $request): JsonResponse
    {
        [$device, $refusal] = $this->guardDevice($request, $this->devices);

        if ($refusal !== null) {
            return $refusal;
        }

        $request->validate([
            'device_id' => ['required', 'string', 'max:50'],
        ]);

        $templates = Fingerprint::query()
            ->with('user:id,identifier_number')
            ->whereHas('user', fn ($query) => $query->whereNotNull('identifier_number'))
            ->latest('id')
            ->get()
            ->filter(fn (Fingerprint $f) => $f->user !== null && filled($f->user->identifier_number))
            ->map(fn (Fingerprint $fingerprint) => [
                'user_id' => $fingerprint->user->identifier_number,
                'template' => $fingerprint->template,
            ])
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => $templates,
        ]);
    }

    /**
     * Daftar nomor induk anggota yang sudah punya template sidik jari.
     */
    public function users(Request $request): JsonResponse
    {
        [$device, $refusal] = $this->guardDevice($request, $this->devices);

        if ($refusal !== null) {
            return $refusal;
        }

        $request->validate([
            'device_id' => ['required', 'string', 'max:50'],
        ]);

        $users = Fingerprint::query()
            ->with('user:id,identifier_number')
            ->whereHas('user', fn ($query) => $query->whereNotNull('identifier_number'))
            ->latest('id')
            ->get()
            ->filter(fn (Fingerprint $f) => $f->user !== null && filled($f->user->identifier_number))
            ->unique(fn (Fingerprint $f) => $f->user->identifier_number)
            ->map(fn (Fingerprint $fingerprint) => [
                'user_id' => $fingerprint->user->identifier_number,
            ])
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => $users,
        ]);
    }
}
