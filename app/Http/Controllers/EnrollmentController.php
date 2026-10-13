<?php

namespace App\Http\Controllers;

use App\Http\Requests\FaceEnrollmentUploadRequest;
use App\Models\FaceEmbedding;
use App\Models\User;
use App\Services\StudentEnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pendaftaran wajah yang dikerjakan sendiri oleh pemilik akun.
 *
 * Halaman ini dipakai anggota sekolah yang belum punya wajah terdaftar. Seluruh
 * aksinya bekerja pada akun yang sedang masuk saja; tidak ada satu pun alamat di
 * sini yang menerima nomor akun dari peramban, sehingga tidak ada jalan untuk
 * mendaftarkan wajah milik orang lain.
 */
class EnrollmentController extends Controller
{
    /**
     * Halaman pendaftaran wajah.
     */
    public function show(Request $request, StudentEnrollmentService $enrollment): View
    {
        return view('enrollment.index', [
            'user' => $request->user(),
            'state' => $enrollment->state($request->user()),
            'variations' => $enrollment->variations(),
        ]);
    }

    /**
     * Terima foto pendaftaran, lalu titipkan pengolahannya ke antrean.
     *
     * Halaman tidak menunggu mesin wajah: satu foto memakan waktu beberapa detik
     * di mesin tanpa kartu grafis, sedangkan pemilik akun mengirimnya dari
     * telepon. Berkasnya langsung disimpan dan kemajuannya dibaca dari panel
     * yang menyegarkan diri sendiri.
     */
    public function store(
        FaceEnrollmentUploadRequest $request,
        StudentEnrollmentService $enrollment,
    ): RedirectResponse {
        $user = $request->user();

        if (($terkunci = $this->locked($user, $enrollment)) !== null) {
            return $terkunci;
        }

        // Dua proses berjalan bersamaan pada satu akun membuat pemeriksaan foto
        // kembar tidak bisa melihat foto kiriman yang lain, dan pemiliknya
        // melihat kemajuan yang tercampur dari dua kelompok berbeda.
        if ($enrollment->hasPending($user)) {
            return back()->with('error', 'Foto Anda sebelumnya masih diproses. Tunggu sampai selesai, lalu kirim foto berikutnya.');
        }

        $replace = $request->boolean('replace');
        $result = $enrollment->submit($user, $request->file('photos') ?? [], $replace);

        return back()->with(
            'status',
            $result['count'].' foto diterima dan sedang diperiksa. '
            .'Buka halaman ini lagi sebentar untuk melihat hasilnya. '
            .($replace ? 'Setelah seluruh fotonya lolos, foto lama Anda diganti dengan yang baru.' : ''),
        );
    }

    /**
     * Kemajuan pengolahan foto milik sendiri, untuk dipantau peramban.
     */
    public function progress(Request $request, StudentEnrollmentService $enrollment): JsonResponse
    {
        $user = $request->user();
        $state = $enrollment->state($user);

        return response()->json([
            'state' => [
                'status' => $state['status'],
                'label' => $state['label'],
                'total' => $state['total'],
                'usable' => $state['usable'],
                'unusable' => $state['unusable'],
                'required' => $state['required'],
                'percent' => $state['percent'],
                'finished' => $state['finished'],
                'pending' => $state['pending'],
                'locked' => $state['locked'],
            ],
            'batches' => $state['batches'],
            'done_url' => $state['finished'] && ! $state['locked'] ? route('enrollment.done') : null,
        ]);
    }

    /**
     * Hapus satu foto wajah milik sendiri.
     */
    public function destroyPhoto(
        Request $request,
        FaceEmbedding $faceEmbedding,
        StudentEnrollmentService $enrollment,
    ): RedirectResponse {
        if (($terkunci = $this->locked($request->user(), $enrollment)) !== null) {
            return $terkunci;
        }

        if (! $enrollment->removePhoto($request->user(), $faceEmbedding)) {
            return back()->with('error', 'Foto itu bukan milik akun Anda.');
        }

        return back()->with('status', 'Satu foto wajah Anda dihapus.');
    }

    /**
     * Hapus seluruh foto wajah milik sendiri, untuk memulai dari awal.
     */
    public function destroyAll(Request $request, StudentEnrollmentService $enrollment): RedirectResponse
    {
        if (($terkunci = $this->locked($request->user(), $enrollment)) !== null) {
            return $terkunci;
        }

        $jumlah = $enrollment->removeAll($request->user());

        if ($jumlah === 0) {
            return back()->with('error', 'Belum ada foto wajah yang tersimpan di akun Anda.');
        }

        return back()->with('status', 'Seluruh foto wajah Anda ('.$jumlah.' foto) dihapus. Silakan mendaftar ulang dari awal.');
    }

    /**
     * Ulangi foto yang gagal pada satu kelompok unggahan milik sendiri.
     */
    public function retry(
        Request $request,
        string $batch,
        StudentEnrollmentService $enrollment,
    ): RedirectResponse {
        if (($terkunci = $this->locked($request->user(), $enrollment)) !== null) {
            return $terkunci;
        }

        $retried = $enrollment->retryBatch($request->user(), $batch);

        if ($retried === 0) {
            return back()->with('error', 'Tidak ada foto yang bisa diulang pada kelompok itu. Foto yang isinya tidak terbaca harus diambil ulang.');
        }

        return back()->with('status', $retried.' foto dimasukkan kembali ke antrean pemeriksaan.');
    }

    /**
     * Balasan penolakan bila pendaftaran wajah akun ini sudah selesai.
     *
     * Diperiksa LEBIH DULU oleh setiap aksi yang mengubah data, bukan hanya
     * disembunyikan dari halaman. Tombol yang disembunyikan melindungi pengguna
     * dari kesalahan, tetapi bukan dari permintaan yang dibuat sendiri di luar
     * halaman.
     */
    private function locked(User $user, StudentEnrollmentService $enrollment): ?RedirectResponse
    {
        if (! $enrollment->isLocked($user)) {
            return null;
        }

        return back()->with('error', StudentEnrollmentService::LockedMessage);
    }

    /**
     * Tutup pendaftaran, lalu arahkan ke halaman konfirmasi.
     */
    public function complete(Request $request, StudentEnrollmentService $enrollment): RedirectResponse
    {
        $user = $request->user();
        $status = $enrollment->refresh($user);

        if ($status !== User::EnrollmentCompleted) {
            return back()->with('error', 'Pendaftaran belum bisa diselesaikan. Pastikan jumlah foto yang lolos sudah mencapai batas minimal dan tidak ada foto yang masih diproses.');
        }

        return redirect()->route('enrollment.done')->with('status', 'Pendaftaran wajah Anda selesai.');
    }

    /**
     * Halaman konfirmasi pendaftaran berhasil.
     */
    public function done(Request $request, StudentEnrollmentService $enrollment): View|RedirectResponse
    {
        $user = $request->user();
        $state = $enrollment->state($user);

        // Halaman ini hanya boleh dibuka oleh pemilik akun yang pendaftarannya
        // benar benar selesai. Tanpa pemeriksaan ini, alamatnya bisa dibuka
        // siapa saja dan seolah-olah wajahnya sudah terdaftar.
        //
        // Keadaan yang tersimpan ikut diperiksa, bukan hanya jumlah fotonya,
        // sebab setelah pendaftarannya selesai pemiliknya boleh menghapus
        // sebagian fotonya tanpa kehilangan halaman konfirmasinya.
        if (! $user->hasCompletedEnrollment() && ! $state['finished']) {
            return redirect()->route('enrollment.show');
        }

        return view('enrollment.done', [
            'user' => $user,
            'state' => $state,
        ]);
    }
}
