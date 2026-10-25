<?php

namespace App\Http\Controllers;

use App\Models\FaceEmbedding;
use App\Models\FacePhotoUpload;
use App\Models\User;
use App\Services\FaceEncodingService;
use App\Services\FaceMatcherService;
use App\Services\FacePhotoQueueService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class FaceDataController extends Controller
{
    /** Jumlah anggota per halaman pada daftar wajah terdaftar. */
    public const PerPage = 10;

    /** Jumlah anggota per halaman pada daftar yang perlu mendaftar ulang. */
    public const EnrollmentPerPage = 12;

    public function index(Request $request, FacePhotoQueueService $queue, FaceMatcherService $matcher): View
    {
        $term = trim((string) $request->query('q', ''));

        $service = app(FaceEncodingService::class);
        $health = $service->health();
        $members = User::query()
            ->search($term !== '' ? $term : null)
            ->with(['faceEmbeddings' => fn ($query) => $query->orderBy('id')])
            ->withCount('faceEmbeddings')
            ->orderBy('name')
            ->paginate(self::PerPage)
            ->withQueryString();

        return view('face.index', [
            'members' => $members,
            'term' => $term,
            // Kesiapannya DITURUNKAN dari keadaan yang sudah dibaca di atas,
            // bukan dipanggil ulang. Memanggil isAvailable() terpisah berarti
            // menghubungi layanan wajah dua kali untuk satu halaman.
            'serviceReady' => $health !== null,
            'serviceUrl' => (string) config('face.service_url'),
            // Keadaan layanan wajah dibaca SEKALI lalu dipakai untuk dua hal:
            // memberi tahu siap atau tidak, dan MEMBANDINGKAN nama model yang
            // dilaporkan mesin dengan yang diharapkan aplikasi.
            //
            // Perbandingan itu penting ditampilkan, sebab dua berkas .env
            // terpisah yang mengaturnya dan berkas .env TIDAK ikut tersalin saat
            // proyek dipindah ke server. Tanpa ditampilkan, keadaannya hanya
            // muncul sebagai kegagalan unggah yang membingungkan.
            'serviceModel' => $health['model'] ?? null,
            'expectedModel' => $service->expectedModel(),
            'stats' => [
                'anggota' => User::query()->count(),
                'berwajah' => User::query()->has('faceEmbeddings')->count(),
                'foto' => FaceEmbedding::query()->count(),
                'batas' => (int) config('face.max_photos_per_user', 20),
                'perlu' => $matcher->aggregatePhotos(),
                // Keadaan pendaftaran mandiri. Dihitung dari kolom status, bukan
                // dari jumlah foto, supaya terlihat juga anggota yang sudah
                // mulai mengirim tetapi belum cukup, dan yang fotonya ditolak.
                'selesai' => User::query()->where('enrollment_status', User::EnrollmentCompleted)->count(),
                'belum' => User::query()->whereNotIn('enrollment_status', [
                    User::EnrollmentCompleted,
                ])->count(),
                'sedang' => User::query()->where('enrollment_status', User::EnrollmentProcessing)->count(),
                'perlu_daftar_ulang' => User::query()->where('enrollment_status', User::EnrollmentNeedsReenrollment)->count(),
                'pendaftaran_terakhir' => User::query()->whereNotNull('enrolled_at')->max('enrolled_at'),
                'wajib_foto' => (int) config('face.enrollment.required_photos', 5),
            ],
            'operators' => User::query()
                ->where('can_scan_face', true)
                ->orderBy('name')
                ->get(),
            'candidates' => User::query()->orderBy('name')->get(['id', 'name', 'identifier_number', 'role']),
            // Daftar yang boleh ditugaskan memegang kamera, beserta kolom
            // penanda tugasnya. Kolom `can_scan_face` WAJIB ikut dipilih: keadaan
            // tugas di halaman ini dibaca dari kolom yang sama dengan yang
            // dipakai penjaga hak akses, sehingga tabel dan hak aksesnya tidak
            // pernah berbeda cerita.
            //
            // Akun yang sudah benar benar bertugas ikut ditampilkan walau
            // perannya murid, sebab penugasan juga bisa diberikan dari halaman
            // Anggota. Tanpa itu akan ada akun yang bisa membuka kamera absen
            // tetapi tidak muncul di sini, sehingga tugasnya tidak bisa dicabut
            // dan keadaannya tidak pernah terbaca admin.
            'assignable' => User::query()
                ->where(fn ($query) => $query
                    ->where('role', '!=', User::RoleMurid)
                    ->orWhere('can_scan_face', true))
                ->orderBy('name')
                ->get(['id', 'name', 'identifier_number', 'role', 'can_scan_face']),
            'maxSubmit' => (int) config('face.max_photos_per_submit', 10),
            'batches' => $queue->batches(),
            'expectedSeconds' => (int) config('face.expected_seconds', 15),
            'outlierPhotos' => $this->outlierPhotos($members, $matcher),
            'photosNeeded' => $matcher->aggregatePhotos(),
            // Daftar anggota yang wajahnya belum selesai, dengan halaman
            // TERPISAH dari daftar wajah terdaftar di atas. Dua daftar dengan
            // halaman masing-masing memakai nama kunci halaman yang berbeda,
            // sebab kalau memakai kunci yang sama, membuka halaman dua pada
            // salah satunya akan menggeser halaman yang lain.
            'pending' => $this->pendingEnrollment(),
        ]);
    }

    /**
     * Anggota yang wajahnya belum selesai, supaya admin tahu siapa yang perlu
     * diingatkan atau diminta mendaftar ulang.
     *
     * @return LengthAwarePaginator<int, User>
     */
    private function pendingEnrollment(): LengthAwarePaginator
    {
        return User::query()
            ->where('enrollment_status', '!=', User::EnrollmentCompleted)
            ->withCount('faceEmbeddings')
            ->orderByRaw(
                'CASE enrollment_status'
                .' WHEN ? THEN 1'
                .' WHEN ? THEN 2'
                .' WHEN ? THEN 3'
                .' ELSE 4 END',
                [User::EnrollmentNeedsReenrollment, User::EnrollmentRejected, User::EnrollmentInProgress],
            )
            ->orderBy('name')
            ->paginate(self::EnrollmentPerPage, ['*'], 'wajah_page')
            ->withQueryString();
    }

    /**
     * Kumpulkan foto yang berbeda jauh dari foto lain milik anggota yang sama.
     *
     * Foto seperti itu biasanya wajahnya tidak terbaca jelas, hasil potret
     * layar, atau bahkan orang yang salah. Kehadirannya berbahaya karena satu
     * foto yang menyimpang membuat anggota tersebut mudah dikenali sebagai
     * orang lain, jadi admin perlu melihatnya di halaman ini.
     *
     * @param  LengthAwarePaginator<int, User>  $members
     * @return array<int, float> id foto => jarak terdekat ke foto saudaranya
     */
    private function outlierPhotos(LengthAwarePaginator $members, FaceMatcherService $matcher): array
    {
        $limit = $matcher->tolerance();
        $outliers = [];

        foreach ($members as $member) {
            if ($member->faceEmbeddings->count() < 2) {
                continue;
            }

            foreach ($matcher->siblingDistances($member->faceEmbeddings) as $id => $distance) {
                if ($distance > $limit) {
                    $outliers[$id] = $distance;
                }
            }
        }

        return $outliers;
    }

    /**
     * Terima foto wajah, lalu titipkan pengubahannya ke antrean.
     *
     * Halaman tidak menunggu mesin wajah: berkasnya langsung disimpan dan
     * jawabannya kembali seketika. Kemajuannya dipantau dari panel Proses
     * pendaftaran wajah.
     */
    public function store(Request $request, FacePhotoQueueService $queue): RedirectResponse
    {
        $max = (int) config('face.max_photos_per_submit', 10);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'photos' => ['required', 'array', 'min:1', 'max:'.$max],
            'photos.*' => ['required', 'image', 'max:5120'],
            'replace' => ['nullable', 'boolean'],
        ], [
            'user_id.required' => 'Pilih anggota yang wajahnya didaftarkan.',
            'user_id.exists' => 'Anggota yang dipilih tidak ditemukan.',
            'photos.required' => 'Pilih foto wajah lebih dulu.',
            'photos.max' => 'Sekali kirim paling banyak '.$max.' foto.',
            'photos.*.image' => 'Berkas wajah harus berupa gambar.',
            'photos.*.max' => 'Ukuran satu foto paling besar 5 MB.',
        ]);

        $member = User::query()->findOrFail((int) $data['user_id']);
        $replace = $request->boolean('replace');
        $result = $queue->enqueue($member, $request->file('photos') ?? [], $request->user(), null, $replace);

        return back()->with('status', $result['count'].' foto wajah '.$member->name.' berhasil diunggah dan masuk antrean pemrosesan.'
            .($replace ? ' Foto lama anggota ini diganti begitu satu foto barunya selesai diproses.' : '')
            .' Kemajuannya bisa dilihat pada panel Proses pendaftaran wajah.');
    }

    /**
     * Kemajuan antrean pemrosesan wajah untuk dipantau peramban.
     */
    public function progress(FacePhotoQueueService $queue): JsonResponse
    {
        return response()->json([
            'batches' => $queue->batches(),
            'unfinished' => $queue->hasUnfinished(),
        ]);
    }

    /**
     * Ulangi foto yang gagal pada satu kelompok unggahan.
     */
    public function retry(string $batch, FacePhotoQueueService $queue): RedirectResponse
    {
        $retried = $queue->retryBatch($batch);

        if ($retried === 0) {
            return back()->with('error', 'Tidak ada foto yang bisa diulang. Foto yang gagal karena isinya tidak terbaca harus diunggah ulang.');
        }

        return back()->with('status', $retried.' foto dimasukkan kembali ke antrean pemrosesan.');
    }

    public function destroyPhoto(FaceEmbedding $faceEmbedding): RedirectResponse
    {
        $owner = $faceEmbedding->user?->name ?? 'anggota';
        $faceEmbedding->delete();

        return back()->with('status', 'Satu foto wajah '.$owner.' dihapus.');
    }

    public function destroyMember(User $member): RedirectResponse
    {
        $rows = $member->faceEmbeddings()->get();
        $total = $rows->count();
        $rows->each->delete();

        return back()->with('status', $total.' foto wajah '.$member->name.' dihapus. Anggota dan riwayat absennya tetap ada.');
    }

    /**
     * Hapus seluruh foto wajah anggota sekaligus membuka pendaftaran ulang seketika (1-klik).
     */
    public function resetAndReenroll(User $member): RedirectResponse
    {
        $rows = $member->faceEmbeddings()->get();
        $total = $rows->count();
        $rows->each->delete();

        FacePhotoUpload::query()
            ->where('user_id', $member->id)
            ->get()
            ->each(function (FacePhotoUpload $upload): void {
                if ($upload->path && Storage::disk('local')->exists($upload->path)) {
                    Storage::disk('local')->delete($upload->path);
                }
                $upload->delete();
            });

        $member->forceFill([
            'enrollment_status' => User::EnrollmentNeedsReenrollment,
            'enrolled_at' => null,
        ])->save();

        return back()->with('status', 'Seluruh foto wajah '.$member->name.' ('.$total.' foto) telah dihapus dan pendaftaran ulang langsung dibuka.');
    }

    /**
     * Menugaskan atau menarik tugas kamera absen.
     */
    public function updateOperator(Request $request, User $member): RedirectResponse
    {
        $assign = $request->boolean('assigned');

        if ($assign && $member->isMurid()) {
            return back()->with('error', 'Anggota berperan murid tidak bisa ditugaskan memegang kamera absen.');
        }

        $member->forceFill(['can_scan_face' => $assign])->save();

        return back()->with('status', $assign
            ? $member->name.' sekarang boleh memakai kamera absen.'
            : $member->name.' tidak lagi boleh memakai kamera absen.');
    }

    /**
     * Minta anggota mendaftarkan wajahnya kembali.
     *
     * Dipakai saat foto yang tersimpan ternyata tidak layak, misalnya karena
     * satu foto milik orang lain ikut terunggah, atau karena seluruh fotonya
     * diambil dengan mesin wajah versi lama.
     *
     * Tandanya dinaikkan, tetapi data lamanya SENGAJA tidak dihapus di sini.
     * Anggota itu masih perlu absen besok, dan menghapus wajahnya sekarang akan
     * membuat dia tidak bisa absen sebelum fotonya yang baru selesai diperiksa.
     * Bila memang data lamanya harus dibuang, admin memakai tombol hapus foto
     * yang sudah tersedia di halaman ini.
     */
    public function requestReenrollment(User $member): RedirectResponse
    {
        $member->forceFill(['enrollment_status' => User::EnrollmentNeedsReenrollment])->save();

        return back()->with('status', $member->name.' diminta mendaftarkan wajahnya kembali. Minta dia membuka halaman pendaftaran wajah setelah masuk.');
    }
}
