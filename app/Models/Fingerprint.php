<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fingerprint extends Model
{
    use HasFactory;

    public const DefaultFingerPosition = 'Jelunjuk Kanan';

    protected $fillable = [
        'user_id',
        'template',
        'finger_position',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Nama posisi jari untuk ditampilkan.
     */
    public static function positionLabel(?string $position): string
    {
        return str_replace('Jelunjuk', 'Telunjuk', $position ?: self::DefaultFingerPosition);
    }
}

