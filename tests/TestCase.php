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
     *
     * @param  list<int>  $activeDays
     */
    protected function seedPrayerTimes(array $activeDays = [1, 2, 3, 4, 5, 6, 7]): void
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
                    'active_days' => $activeDays,
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
     * `$faceSize` dan `$model` dipakai menguji penolakan potret yang wajahnya
     * terlalu kecil dan penolakan angka wajah dari model yang berbeda. `$status`
     * bisa diisi langsung bila pengujian ingin balasan yang ditolak mesin wajah,
     * misalnya `multiple_faces`.
     *
     * `$faces` dipakai jalur absen banyak wajah: satu entri per wajah, dengan
     * kunci `encoding` (null berarti angkanya gagal dibaca), `box`, dan
     * `face_size`. Bila `$faces` kosong, jawabannya disusun dari `$encoding`
     * dan `$faceCount` seperti sebelumnya, sehingga pengujian lama tidak perlu
     * diubah.
     *
     * @param  array<int, float>|null  $encoding
     * @param  array<int, array<string, mixed>>  $faces
     * @param  array<int, array<int, float>>  $sequence  angka wajah yang dikembalikan bergantian untuk setiap foto
     */
    protected function fakeFaceService(
        ?array $encoding = null,
        int $faceCount = 1,
        bool $available = true,
        ?string $photo = null,
        int $faceSize = 0,
        ?string $model = null,
        ?string $status = null,
        array $faces = [],
        int $frameWidth = 640,
        int $frameHeight = 480,
        array $sequence = [],
    ): void {
        $embedding = $encoding ?? array_fill(0, FaceEmbedding::Dimensions, 0.01);

        $this->app->instance(FaceEncodingService::class, new class($embedding, $faceCount, $available, $photo, $faceSize, $model, $status, $faces, $frameWidth, $frameHeight, $sequence) extends FaceEncodingService
        {
            /** Apakah foto bukti diminta pada pemanggilan terakhir. */
            public bool $askedForPhoto = false;

            /** Berapa kali jalur deteksi banyak wajah dipanggil. */
            public int $detectCalls = 0;

            /** Berapa kali jalur pendaftaran mandiri dipanggil. */
            public int $enrollmentCalls = 0;

            /** Berapa kali angka wajah dihitung dari sebuah foto. */
            private int $encodeCalls = 0;

            /**
             * @param  array<int, float>  $embedding
             * @param  array<int, array<string, mixed>>  $faces
             * @param  array<int, array<int, float>>  $sequence
             */
            public function __construct(
                private array $embedding,
                private int $faceCount,
                private bool $available,
                private ?string $photo,
                private int $faceSize,
                private ?string $model,
                private ?string $status,
                private array $faces,
                private int $frameWidth,
                private int $frameHeight,
                private array $sequence = [],
            ) {}

            public function encode(UploadedFile $image, bool $withPhoto = false): array
            {
                return $this->encodeContents('potret uji', $withPhoto);
            }

            /**
             * Keadaan layanan menurut tiruan ini.
             *
             * WAJIB ditimpa, sebab bawaan induknya menghubungi layanan sungguhan
             * lewat HTTP. Tanpa ini, halaman /wajah akan bergantung pada apakah
             * mesin wajah sedang menyala di komputer yang menjalankan pengujian,
             * dan hasilnya bisa berbeda antara dua komputer.
             *
             * Nama modelnya diambil dari `$model` supaya pengujian juga bisa
             * memalsukan keadaan "mesin memakai model yang berbeda", yaitu
             * keadaan yang terjadi saat berkas .env tidak ikut tersalin ketika
             * proyek dipindah ke server.
             */
            public function health(): ?array
            {
                if (! $this->available) {
                    return null;
                }

                return [
                    'model' => $this->model ?? FaceEmbedding::DefaultModel,
                    'ready' => true,
                    'dimensions' => FaceEmbedding::Dimensions,
                ];
            }

            public function encodeContents(string $contents, bool $withPhoto = false): array
            {
                $this->askedForPhoto = $withPhoto;
                $this->encodeCalls++;

                if (! $this->available) {
                    return [
                        'status' => self::StatusUnavailable,
                        'encoding' => null,
                        'face_count' => 0,
                        'face_size' => 0,
                        'model' => null,
                        'message' => $this->unavailableMessage(),
                        'photo' => null,
                    ];
                }

                if ($this->status !== null) {
                    return [
                        'status' => $this->status,
                        'encoding' => null,
                        'face_count' => $this->faceCount,
                        'face_size' => $this->faceSize,
                        'model' => $this->model,
                        'message' => 'Balasan uji '.$this->status.'.',
                        'photo' => null,
                    ];
                }

                if ($this->faceCount < 1) {
                    return [
                        'status' => self::StatusNoFace,
                        'encoding' => null,
                        'face_count' => 0,
                        'face_size' => 0,
                        'model' => $this->model,
                        'message' => 'Wajah tidak terlihat. Dekatkan wajah ke kamera dan pastikan cahaya cukup.',
                        'photo' => null,
                    ];
                }

                // Aturan ukuran wajah paling kecil ikut ditiru di sini. Aturan
                // itu ada di dalam layanan induk, dan tiruan ini menggantikan
                // seluruh kelasnya, sehingga tanpa salinan di sini pengujian
                // halaman pendaftaran akan menganggap foto sekecil apa pun
                // diterima padahal layanan sungguhannya menolak.
                $minSize = (int) config('face.min_face_size', 80);

                if ($this->faceSize > 0 && $minSize > 0 && $this->faceSize < $minSize) {
                    return [
                        'status' => self::StatusFaceTooSmall,
                        'encoding' => null,
                        'face_count' => $this->faceCount,
                        'face_size' => $this->faceSize,
                        'model' => $this->model,
                        'message' => 'Wajah terlalu kecil di layar. Dekatkan wajah ke kamera, lalu coba lagi.',
                        'photo' => null,
                    ];
                }

                return [
                    'status' => self::StatusSuccess,
                    'encoding' => $this->nextEmbedding(),
                    'face_count' => $this->faceCount,
                    'face_size' => $this->faceSize,
                    'model' => $this->model,
                    'message' => 'Wajah terbaca.',
                    'photo' => $this->photo,
                ];
            }

            /**
             * Jalur pendaftaran mandiri.
             *
             * Tiruan ini tidak bisa menilai mutu foto, sebab penilaian itu
             * dikerjakan mesin wajah Python. Yang ditiru adalah balasannya,
             * sehingga aturan mutu yang diputuskan aplikasi tetap bisa diuji
             * dengan mengisi `$status` bernilai `too_dark` dan sejenisnya.
             */
            public function encodeForEnrollment(string $contents): array
            {
                $this->enrollmentCalls++;

                return $this->encodeContents($contents);
            }

            /**
             * Angka wajah untuk pemanggilan berikutnya.
             *
             * Bila `$sequence` diisi, setiap pemanggilan memakai angka yang
             * berbeda dari daftarnya secara bergilir. Ini diperlukan pengujian
             * pemeriksaan foto kembar: dengan angka yang selalu sama, foto
             * kedua dan seterusnya akan selalu dianggap kembar.
             *
             * @return array<int, float>
             */
            private function nextEmbedding(): array
            {
                if ($this->sequence === []) {
                    return $this->embedding;
                }

                $index = ($this->encodeCalls - 1) % count($this->sequence);

                return $this->sequence[$index];
            }

            /**
             * Balasan jalur deteksi banyak wajah.
             *
             * Kotaknya sengaja dibuat berjarak jauh satu sama lain supaya
             * pengujian dapat memastikan kotak mana milik wajah yang mana.
             */
            public function detectContents(string $contents, bool $withPhoto = false): array
            {
                $this->detectCalls++;
                $this->askedForPhoto = $withPhoto;

                $kosong = [
                    'status' => self::StatusInvalid,
                    'message' => '',
                    'width' => $this->frameWidth,
                    'height' => $this->frameHeight,
                    'photo' => null,
                    'faces' => [],
                ];

                if (! $this->available) {
                    return array_merge($kosong, [
                        'status' => self::StatusUnavailable,
                        'message' => $this->unavailableMessage(),
                    ]);
                }

                if ($this->status !== null) {
                    return array_merge($kosong, [
                        'status' => $this->status,
                        'message' => 'Balasan uji '.$this->status.'.',
                    ]);
                }

                if ($this->faces !== []) {
                    $daftar = [];

                    foreach ($this->faces as $index => $wajah) {
                        $daftar[] = [
                            'index' => $index,
                            'top' => (int) ($wajah['box']['top'] ?? 100 + $index * 10),
                            'right' => (int) ($wajah['box']['right'] ?? 200 + $index * 200),
                            'bottom' => (int) ($wajah['box']['bottom'] ?? 250 + $index * 10),
                            'left' => (int) ($wajah['box']['left'] ?? 50 + $index * 200),
                            'face_size' => (int) ($wajah['face_size'] ?? 150),
                            'encoding' => $wajah['encoding'] ?? null,
                            'error' => ($wajah['encoding'] ?? null) === null ? 'encoding_failed' : null,
                        ];
                    }

                    return array_merge($kosong, [
                        'status' => self::StatusSuccess,
                        'message' => count($daftar).' wajah terbaca.',
                        'photo' => $withPhoto ? $this->photo : null,
                        'faces' => $daftar,
                    ]);
                }

                if ($this->faceCount < 1) {
                    return array_merge($kosong, ['status' => self::StatusNoFace, 'message' => 'Wajah tidak terlihat.']);
                }

                $daftar = [];

                for ($index = 0; $index < $this->faceCount; $index++) {
                    $daftar[] = [
                        'index' => $index,
                        'top' => 100 + $index * 10,
                        'right' => 200 + $index * 200,
                        'bottom' => 250 + $index * 10,
                        'left' => 50 + $index * 200,
                        'face_size' => $this->faceSize > 0 ? $this->faceSize : 150,
                        'encoding' => $this->embedding,
                        'error' => null,
                    ];
                }

                return array_merge($kosong, [
                    'status' => self::StatusSuccess,
                    'message' => count($daftar).' wajah terbaca.',
                    'photo' => $withPhoto ? $this->photo : null,
                    'faces' => $daftar,
                ]);
            }
        });
    }
}
