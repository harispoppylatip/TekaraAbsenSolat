<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'identifier_number',
    'role',
    'class_name',
    'phone_number',
    'can_scan_face',
    'last_login_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const RoleAdmin = 'admin';

    public const RolePetugas = 'petugas';

    public const RoleMurid = 'murid';

    public const DefaultPassword = 'solat123';

    /**
     * Atribut yang perlu diubah bentuknya.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'can_scan_face' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<FaceEmbedding, $this>
     */
    public function faceEmbeddings(): HasMany
    {
        return $this->hasMany(FaceEmbedding::class);
    }

    /**
     * @return HasMany<SolatAttendanceLog, $this>
     */
    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(SolatAttendanceLog::class);
    }

    /**
     * @return HasMany<SolatSession, $this>
     */
    public function openedSessions(): HasMany
    {
        return $this->hasMany(SolatSession::class, 'opened_by');
    }

    public function isAdmin(): bool
    {
        return $this->role === self::RoleAdmin;
    }

    public function isPetugas(): bool
    {
        return $this->role === self::RolePetugas;
    }

    public function isMurid(): bool
    {
        return $this->role === self::RoleMurid;
    }

    /**
     * Petugas yang ditugaskan memegang kamera absen.
     */
    public function isCameraOperator(): bool
    {
        return (bool) $this->can_scan_face;
    }

    public function roleLabel(): string
    {
        return (string) (config('solat.roles')[$this->role] ?? $this->role);
    }

    public function homeRoute(): string
    {
        return $this->isMurid() ? 'murid.riwayat' : 'dashboard';
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $initials = '';

        foreach (array_slice(array_values(array_filter($parts)), 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $initials !== '' ? $initials : 'A';
    }

    /**
     * Sandi bawaan anggota: murid memakai nomor induk, lainnya memakai
     * sandi umum yang wajib diganti.
     */
    public static function defaultPasswordFor(string $role, ?string $identifier = null): string
    {
        if ($role === self::RoleMurid && filled($identifier)) {
            return (string) $identifier;
        }

        return self::DefaultPassword;
    }

    /**
     * @return array<string, string>
     */
    public static function roles(): array
    {
        return (array) config('solat.roles');
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $like = '%'.trim($term).'%';

        $query->where(function (Builder $inner) use ($like) {
            $inner->where('name', 'like', $like)
                ->orWhere('identifier_number', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('class_name', 'like', $like);
        });
    }
}
