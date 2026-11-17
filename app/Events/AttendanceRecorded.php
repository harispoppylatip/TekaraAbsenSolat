<?php

namespace App\Events;

use App\Models\Setting;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Sinyal ringan saat ada absensi baru tercatat.
 *
 * Hanya mengirim penanda umum (tanpa data pribadi) agar halaman /layar,
 * /kamera, dan /login segera menyegarkan data saat mode WebSocket aktif.
 */
class AttendanceRecorded implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public string $prayer = '',
        public string $status = 'hadir',
        public string $source = 'face',
    ) {}

    public function broadcastWhen(): bool
    {
        return Setting::realtimeMode() === 'websocket';
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('solat.realtime'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'attendance.recorded';
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return [
            'prayer' => $this->prayer,
            'status' => $this->status,
            'source' => $this->source,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
