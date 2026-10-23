<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'stream_type',
    'stream_url',
    'snapshot_url',
    'location',
    'is_active',
    'notes',
])]
class Camera extends Model
{
    public const TypeWebStream = 'web_stream';

    public const TypeRtsp = 'rtsp';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function isWebStream(): bool
    {
        return $this->stream_type === self::TypeWebStream;
    }

    public function isRtsp(): bool
    {
        return $this->stream_type === self::TypeRtsp;
    }

    public function typeLabel(): string
    {
        return match ($this->stream_type) {
            self::TypeRtsp => 'RTSP CCTV',
            default => 'Web Stream (HTTP/fMP4)',
        };
    }

    public function statusLabel(): string
    {
        return $this->is_active ? 'Aktif' : 'Dimatikan';
    }

    /**
     * @param  Builder<Camera>  $query
     * @return Builder<Camera>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
