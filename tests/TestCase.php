<?php

namespace Tests;

use App\Models\FaceEmbedding;
use App\Models\PrayerTime;
use App\Models\SolatSession;
use App\Models\User;
use App\Services\FaceEncodingService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

abstract class TestCase extends BaseTestCase
{
    private static int $sequence = 0;

    /**
     * Buat anggota uji. Sandi bawaannya dianggap sudah diganti supaya pengujian
     * tidak teralihkan ke halaman ganti sandi, kecuali bila pengujian memang
     * ingin keadaan itu dengan mengirim `password_changed_at` bernilai null.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function makeUser(string $role = User::RoleMurid, array $attributes = []): User
    {
        self::$sequence++;
        $number = str_pad((string) self::$sequence, 3, '0', STR_PAD_LEFT);

        $changedAt = array_key_exists('password_changed_at', $attributes)
            ? $attributes['password_changed_at']
            : Carbon::now();

        unset($attributes['password_changed_at']);

        $user = User::create(array_merge([
            'name' => 'Anggota Uji '.$number,
            'email' => 'anggota'.$number.'@contoh.test',
            'identifier_number' => $role === User::RoleMurid ? '260'.$number : 'PTG-'.$number,
            'role' => $role,
            'password' => 'rahasia123',
        ], $attributes));

        $user->forceFill(['password_changed_at' => $changedAt])->save();

        return $user->refresh();
    }

    /**
     * Isi waktu solat dari bawaan aplikasi.
     */
    protected function seedPrayerTimes(): void
    {
        foreach (config('solat.prayer_defaults') as $prayer) {
            PrayerTime::updateOrCreate(
                ['prayer_name' => $prayer['prayer_name']],
                [
                    'label' => $prayer['label'],
                    'start_time' => $prayer['start_time'],
                    'end_time' => $prayer['end_time'],
                    'late_minutes' => config('solat.late_minutes'),
                    'sort_order' => $prayer['sort_order'],
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * Buat satu sesi absen. Jamnya mengikuti waktu solat yang sudah disemai.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function makeSession(string $prayerName = 'zuhur', ?string $date = null, array $attributes = []): SolatSession
    {
        $prayer = PrayerTime::query()->where('prayer_name', $prayerName)->first()
            ?? PrayerTime::query()->active()->ordered()->firstOrFail();

        return SolatSession::create(array_merge([
            'prayer_name' => $prayer->prayer_name,
            'date' => $date ?? Carbon::today()->toDateString(),
            'start_time' => $prayer->start_time,
            'end_time' => $prayer->end_time,
            'status' => SolatSession::StatusBelumDibuka,
        ], $attributes));
    }

    /**
     * Ganti layanan wajah dengan jawaban tetap supaya pengujian tidak perlu
     * menjalankan mesin wajah Python.
     *
     * `$photo` berisi isi berkas bukti yang dikirim mesin wajah, sedangkan
     * `$withPhoto` mencatat apakah pemanggilnya memang meminta foto bukti.
     *
     * @param  array<int, float>|null  $encoding
     */
    protected function fakeFaceService(
        ?array $encoding = null,
        int $faceCount = 1,
        bool $available = true,
        ?string $photo = null,
    ): void {
        $embedding = $encoding ?? array_fill(0, FaceEmbedding::Dimensions, 0.01);

        $this->app->instance(FaceEncodingService::class, new class($embedding, $faceCount, $available, $photo) extends FaceEncodingService
        {
            /** Apakah foto bukti diminta pada pemanggilan terakhir. */
            public bool $askedForPhoto = false;

            /**
             * @param  array<int, float>  $embedding
             */
            public function __construct(
                private array $embedding,
                private int $faceCount,
                private bool $available,
                private ?string $photo,
            ) {}

            public function encode(UploadedFile $image, bool $withPhoto = false): array
            {
                return $this->encodeContents('potret uji', $withPhoto);
            }

            public function encodeContents(string $contents, bool $withPhoto = false): array
            {
                $this->askedForPhoto = $withPhoto;

                if (! $this->available) {
                    return [
                        'status' => self::StatusUnavailable,
                        'encoding' => null,
                        'face_count' => 0,
                        'message' => $this->unavailableMessage(),
                        'photo' => null,
                    ];
                }

                if ($this->faceCount < 1) {
                    return [
                        'status' => self::StatusNoFace,
                        'encoding' => null,
                        'face_count' => 0,
                        'message' => 'Wajah tidak terlihat. Dekatkan wajah ke kamera dan pastikan cahaya cukup.',
                        'photo' => null,
                    ];
                }

                return [
                    'status' => self::StatusSuccess,
                    'encoding' => $this->embedding,
                    'face_count' => $this->faceCount,
                    'message' => 'Wajah terbaca.',
                    'photo' => $this->photo,
                ];
            }

            public function isAvailable(): bool
            {
                return $this->available;
            }
        });
    }
}
