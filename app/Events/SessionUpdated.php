<?php

namespace App\Events;

use App\Models\Setting;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Sinyal ringan saat sesi solat atau sesi uji coba dibuka/ditutup.
 *
 * Memicu halaman /ringkasan, /sesi-absen, /absen-wajah, /layar, /kamera, dan /login
 * untuk menyegarkan status sesi seketika tanpa perlu refresh manual.
 */
class SessionUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public string $action = 'updated',
        public string $prayer = '',
        public bool $isTest = false,
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
        return 'session.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'prayer' => $this->prayer,
            'is_test' => $this->isTest,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
