<?php

namespace App\Http\Controllers;

use App\Models\FaceEmbedding;
use App\Models\User;
use App\Services\FaceEncodingService;
use App\Services\FaceMatcherService;
use App\Services\FacePhotoQueueService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FaceDataController extends Controller
{
    /** Jumlah anggota per halaman pada daftar wajah terdaftar. */
    public const PerPage = 10;

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
        ]);
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
        $total = $member->faceEmbeddings()->count();
        $member->faceEmbeddings()->delete();

        return back()->with('status', $total.' foto wajah '.$member->name.' dihapus. Anggota dan riwayat absennya tetap ada.');
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
}
