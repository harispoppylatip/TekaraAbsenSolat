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
    'enrollment_status',
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

    /** Belum pernah membuka halaman pendaftaran wajah. */
    public const EnrollmentNotStarted = 'not_started';

    /** Sudah mengirim sebagian foto, tetapi jumlahnya belum cukup. */
    public const EnrollmentInProgress = 'in_progress';

    /** Foto sudah dikirim dan sedang diubah menjadi angka wajah di antrean. */
    public const EnrollmentProcessing = 'processing';

    /** Jumlah foto wajahnya sudah cukup dan sudah tersimpan. */
    public const EnrollmentCompleted = 'completed';

    /** Foto terakhir ditolak, jadi wajahnya belum terdaftar. */
    public const EnrollmentRejected = 'rejected';

    /** Angka wajahnya ada, tetapi berasal dari mesin atau model yang berbeda. */
    public const EnrollmentNeedsReenrollment = 'needs_reenrollment';

    /**
     * Seluruh keadaan pendaftaran wajah yang dikenali.
     *
     * Dikumpulkan di satu tempat supaya migrasi, halaman pendaftaran, panel
     * admin, dan pengujian tidak pernah memakai tulisan yang berbeda untuk
     * keadaan yang sama.
     *
     * @var array<int, string>
     */
    public const EnrollmentStatuses = [
        self::EnrollmentNotStarted,
        self::EnrollmentInProgress,
        self::EnrollmentProcessing,
        self::EnrollmentCompleted,
        self::EnrollmentRejected,
        self::EnrollmentNeedsReenrollment,
    ];

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
            'enrollment_started_at' => 'datetime',
            'enrolled_at' => 'datetime',
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

    public function enrollmentStatus(): string
    {
        $status = (string) $this->enrollment_status;

        return in_array($status, self::EnrollmentStatuses, true) ? $status : self::EnrollmentNotStarted;
    }

    public function enrollmentLabel(): string
    {
        return (string) (config('solat.enrollment_status_labels')[$this->enrollmentStatus()] ?? $this->enrollmentStatus());
    }

    /**
     * Pendaftaran wajahnya sudah selesai, jadi absennya bisa memakai wajah.
     */
    public function hasCompletedEnrollment(): bool
    {
        return $this->enrollmentStatus() === self::EnrollmentCompleted;
    }

    /**
     * Keadaan yang masih boleh dilanjutkan atau diperbaiki sendiri pemiliknya.
     *
     * `processing` dan `needs_reenrollment` ikut di sini. `needs_reenrollment`
     * karena pemiliknya memang harus mengirim ulang fotonya sendiri; kalau
     * tidak, angka wajah dari model lama akan terus tinggal di basis data tanpa
     * pernah bisa dipakai. `processing` karena kiriman yang sedang diperiksa
     * belum tentu berhasil, dan tautan ke halaman pendaftarannya harus tetap ada
     * supaya dia bisa melihat hasilnya.
     */
    public function needsEnrollment(): bool
    {
        return in_array($this->enrollmentStatus(), [
            self::EnrollmentNotStarted,
            self::EnrollmentInProgress,
            self::EnrollmentProcessing,
            self::EnrollmentRejected,
            self::EnrollmentNeedsReenrollment,
        ], true);
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
