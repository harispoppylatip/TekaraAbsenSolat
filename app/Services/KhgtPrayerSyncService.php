<?php

namespace App\Services;

use App\Models\PrayerTime;
use App\Models\Setting;
use App\Models\SolatSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class KhgtPrayerSyncService
{
    public const DefaultLatitude = '-0.464768';

    public const DefaultLongitude = '117.138239';

    public const DefaultWindowMinutes = 45;

    public const KeyAutoSync = 'khgt_auto_sync';

    public const KeyLatitude = 'khgt_latitude';

    public const KeyLongitude = 'khgt_longitude';

    public const KeyWindowMinutes = 'khgt_window_minutes';

    public const KeyLastSyncedAt = 'khgt_last_synced_at';

    public const KeyLastSyncedDate = 'khgt_last_synced_date';

    public const KeyLastLocation = 'khgt_last_location';

    public const KeyLastTimes = 'khgt_last_times';

    /**
     * Daftar 5 waktu sholat yang disinkronkan dari API KHGT Muhammadiyah.
     *
     * @var array<string, array{label: string, sort: int}>
     */
    private const PrayerMap = [
        'subuh' => ['label' => 'Subuh', 'sort' => 1],
        'zuhur' => ['label' => 'Zuhur', 'sort' => 2],
        'ashar' => ['label' => 'Ashar', 'sort' => 3],
        'maghrib' => ['label' => 'Maghrib', 'sort' => 4],
        'isya' => ['label' => 'Isya', 'sort' => 5],
    ];

    /**
     * Ambil konfigurasi sinkronisasi KHGT saat ini.
     *
     * @return array{
     *     auto_sync: bool,
     *     latitude: string,
     *     longitude: string,
     *     window_minutes: int,
     *     last_synced_at: ?string,
     *     last_synced_date: ?string,
     *     last_location: ?string,
     *     last_times: array<string, string>,
     *     api_url: string
     * }
     */
    public function config(): array
    {
        $lat = trim((string) Setting::get(self::KeyLatitude, self::DefaultLatitude));
        $long = trim((string) Setting::get(self::KeyLongitude, self::DefaultLongitude));
        $window = (int) Setting::get(self::KeyWindowMinutes, self::DefaultWindowMinutes);
        $autoSyncRaw = Setting::get(self::KeyAutoSync, '1');
        $lastTimesRaw = (string) Setting::get(self::KeyLastTimes, '');
        $lastTimes = $lastTimesRaw !== '' ? (json_decode($lastTimesRaw, true) ?: []) : [];

        $lat = $lat !== '' ? $lat : self::DefaultLatitude;
        $long = $long !== '' ? $long : self::DefaultLongitude;
        $window = $window >= 10 && $window <= 180 ? $window : self::DefaultWindowMinutes;

        return [
            'auto_sync' => in_array($autoSyncRaw, ['1', 'true', true, 1], true),
            'latitude' => $lat,
            'longitude' => $long,
            'window_minutes' => $window,
            'last_synced_at' => Setting::get(self::KeyLastSyncedAt),
            'last_synced_date' => Setting::get(self::KeyLastSyncedDate),
            'last_location' => Setting::get(self::KeyLastLocation),
            'last_times' => is_array($lastTimes) ? $lastTimes : [],
            'api_url' => 'https://khgt.muhammadiyah.or.id/prayer?lat='.urlencode($lat).'&long='.urlencode($long),
        ];
    }

    /**
     * Ambil jadwal waktu sholat dari API KHGT Muhammadiyah dan perbarui database.
     *
     * @return array{ok: bool, message: string, times?: array<string, string>, location?: string}
     */
    public function sync(
        ?string $latitude = null,
        ?string $longitude = null,
        ?int $windowMinutes = null,
        ?bool $autoSync = null,
    ): array {
        $current = $this->config();

        $lat = trim($latitude ?? $current['latitude']);
        $long = trim($longitude ?? $current['longitude']);
        $window = $windowMinutes ?? $current['window_minutes'];
        $window = max(10, min(180, $window));

        if ($latitude !== null) {
            Setting::set(self::KeyLatitude, $lat);
        }
        if ($longitude !== null) {
            Setting::set(self::KeyLongitude, $long);
        }
        if ($windowMinutes !== null) {
            Setting::set(self::KeyWindowMinutes, (string) $window);
        }
        if ($autoSync !== null) {
            Setting::set(self::KeyAutoSync, $autoSync ? '1' : '0');
        }

        try {
            $response = Http::timeout(12)
                ->retry(2, 300)
                ->acceptJson()
                ->withUserAgent('AbsenSolat-SMKIM4/1.0')
                ->get('https://khgt.muhammadiyah.or.id/prayer', [
                    'lat' => $lat,
                    'long' => $long,
                ]);

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'message' => 'Gagal menghubungi server KHGT Muhammadiyah (HTTP '.$response->status().').',
                ];
            }

            $payload = $response->json();
            $times = is_array($payload) ? ($payload['times'] ?? null) : null;

            if (! is_array($times) || empty($times['subuh']) || empty($times['zuhur'])) {
                return [
                    'ok' => false,
                    'message' => 'Balasan API KHGT Muhammadiyah tidak memuat jadwal sholat yang valid.',
                ];
            }
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Tidak dapat terhubung ke API KHGT Muhammadiyah: '.$e->getMessage(),
            ];
        }

        $activeDays = app(SolatSessionService::class)->activeDays();
        $defaultLate = (int) config('solat.late_minutes', 10);
        $updatedCount = 0;

        foreach (self::PrayerMap as $prayerName => $meta) {
            $rawStart = isset($times[$prayerName]) ? trim((string) $times[$prayerName]) : '';
            if (! preg_match('/^\d{2}:\d{2}$/', $rawStart)) {
                continue;
            }

            $endTime = Carbon::createFromFormat('H:i', $rawStart)
                ->addMinutes($window)
                ->format('H:i');

            $existing = PrayerTime::query()->where('prayer_name', $prayerName)->first();

            if ($existing !== null) {
                $existing->update([
                    'start_time' => $rawStart,
                    'end_time' => $endTime,
                ]);
            } else {
                PrayerTime::create([
                    'prayer_name' => $prayerName,
                    'label' => $meta['label'],
                    'start_time' => $rawStart,
                    'end_time' => $endTime,
                    'late_minutes' => $defaultLate,
                    'sort_order' => $meta['sort'],
                    'is_active' => true,
                    'active_days' => $activeDays,
                ]);
            }

            // Perbarui sesi hari ini yang belum disentuh manusia supaya langsung mengikuti jam KHGT terbaru
            SolatSession::query()
                ->real()
                ->forDate(today())
                ->where('prayer_name', $prayerName)
                ->where('status', SolatSession::StatusBelumDibuka)
                ->whereNull('opened_by')
                ->whereNull('closed_by')
                ->update([
                    'start_time' => $rawStart,
                    'end_time' => $endTime,
                ]);

            $updatedCount++;
        }

        $locationParts = array_filter([
            $payload['detail']['location']['amenity'] ?? null,
            $payload['detail']['location']['road'] ?? null,
            $payload['detail']['location']['village'] ?? null,
            $payload['detail']['location']['city'] ?? null,
        ]);
        $locationLabel = $locationParts !== []
            ? implode(', ', $locationParts)
            : 'Koordinat '.$lat.', '.$long;

        $cleanTimes = [];
        foreach (['subuh', 'terbit', 'zuhur', 'ashar', 'maghrib', 'isya'] as $key) {
            if (! empty($times[$key])) {
                $cleanTimes[$key] = (string) $times[$key];
            }
        }

        $now = now();
        Setting::set(self::KeyLastSyncedAt, $now->translatedFormat('d M Y, H:i:s').' WITA');
        Setting::set(self::KeyLastSyncedDate, $now->toDateString());
        Setting::set(self::KeyLastLocation, $locationLabel);
        Setting::set(self::KeyLastTimes, json_encode($cleanTimes));

        return [
            'ok' => true,
            'message' => $updatedCount.' waktu sholat berhasil disinkronkan dari KHGT Muhammadiyah ('.$locationLabel.').',
            'times' => $cleanTimes,
            'location' => $locationLabel,
        ];
    }

    /**
     * Pastikan jadwal sholat hari ini sudah disinkronkan bila fitur sinkron otomatis aktif.
     */
    public function ensureSyncedToday(): void
    {
        $cfg = $this->config();

        if (! $cfg['auto_sync']) {
            return;
        }

        $todayStr = now()->toDateString();
        if ($cfg['last_synced_date'] === $todayStr) {
            return;
        }

        $this->sync();
    }
}
