<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\FaceEmbedding;
use App\Models\FacePhotoUpload;
use App\Models\SolatAttendanceLog;
use App\Models\SolatSession;
use App\Models\User;
use App\Services\FacePhotoQueueService;
use App\Services\SolatProofService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Halaman pengaturan dan pembersihan data sistem khusus Administrator.
 *
 * Setiap aksi penghapusan membersihkan baik baris basis data maupun berkas
 * fisik di cakram privat (foto bukti absen, foto wajah, dan antrean unggahan)
 * supaya tidak menyisakan berkas yatim di penyimpanan.
 */
class SettingController extends Controller
{
    public function index(): View
    {
        return view('settings.index', [
            'attendanceCount' => SolatAttendanceLog::query()->count(),
            'sessionCount' => SolatSession::query()->count(),
            'faceCount' => FaceEmbedding::query()->count(),
            'queueCount' => FacePhotoUpload::query()->count(),
            'memberCount' => User::query()->where('role', '!=', User::RoleAdmin)->count(),
            'adminCount' => User::query()->where('role', User::RoleAdmin)->count(),
            'deviceCount' => Device::query()->count(),
        ]);
    }

    /**
     * Hapus seluruh riwayat kehadiran beserta foto bukti absen.
     */
    public function clearAttendance(): RedirectResponse
    {
        $count = SolatAttendanceLog::query()->count();

        Storage::disk(SolatProofService::Disk)->deleteDirectory(SolatProofService::Folder);
        SolatAttendanceLog::query()->delete();

        return back()->with(
            'status',
            $count.' catatan riwayat kehadiran beserta seluruh foto bukti absen berhasil dihapus permanen.',
        );
    }

    /**
     * Hapus seluruh sesi absen (reguler & uji coba) beserta riwayat kehadiran dan foto buktinya.
     */
    public function clearSessions(): RedirectResponse
    {
        $sessionCount = SolatSession::query()->count();
        $logCount = SolatAttendanceLog::query()->count();

        Storage::disk(SolatProofService::Disk)->deleteDirectory(SolatProofService::Folder);
        SolatAttendanceLog::query()->delete();
        SolatSession::query()->delete();

        return back()->with(
            'status',
            $sessionCount.' sesi absen dan '.$logCount.' riwayat kehadiran beserta foto buktinya berhasil dihapus.',
        );
    }

    /**
     * Hapus seluruh sidik wajah, foto wajah, dan antrean pendaftaran wajah,
     * lalu kembalikan status pendaftaran wajah seluruh pengguna ke awal.
     */
    public function clearFaces(): RedirectResponse
    {
        $faceCount = FaceEmbedding::query()->count();
        $queueCount = FacePhotoUpload::query()->count();

        Storage::disk('local')->deleteDirectory('face-photos');
        Storage::disk('local')->deleteDirectory(FacePhotoQueueService::Folder);

        FaceEmbedding::query()->delete();
        FacePhotoUpload::query()->delete();

        User::query()->update([
            'enrollment_status' => User::EnrollmentNotStarted,
            'enrolled_at' => null,
        ]);

        return back()->with(
            'status',
            $faceCount.' foto wajah terdaftar dan '.$queueCount.' antrean foto berhasil dihapus. Status pendaftaran wajah seluruh akun telah direset.',
        );
    }

    /**
     * Hapus seluruh akun Murid dan Petugas beserta data wajah dan riwayat absennya.
     * Akun Administrator tidak pernah ikut dihapus.
     */
    public function clearMembers(SolatProofService $proofs): RedirectResponse
    {
        $members = User::query()->where('role', '!=', User::RoleAdmin)->get();
        $count = $members->count();

        if ($count === 0) {
            return back()->with('error', 'Tidak ada akun murid atau petugas yang bisa dihapus.');
        }

        foreach ($members as $member) {
            $proofs->forgetForUser($member);
            Storage::disk('local')->deleteDirectory('face-photos/'.$member->getKey());
        }

        $memberIds = $members->modelKeys();

        FacePhotoUpload::query()->whereIn('user_id', $memberIds)->delete();
        FaceEmbedding::query()->whereIn('user_id', $memberIds)->delete();
        SolatAttendanceLog::query()->whereIn('user_id', $memberIds)->delete();
        User::query()->whereIn('id', $memberIds)->delete();

        return back()->with(
            'status',
            $count.' akun anggota (murid & petugas) beserta data wajah dan riwayat absennya berhasil dihapus. Akun Administrator tetap aman.',
        );
    }

    /**
     * Hapus seluruh perangkat terdaftar.
     */
    public function clearDevices(): RedirectResponse
    {
        $count = Device::query()->count();
        Device::query()->delete();

        return back()->with(
            'status',
            $count.' perangkat terdaftar berhasil dihapus.',
        );
    }

    /**
     * Hapus seluruh data operasional sistem sekaligus (riwayat absen, sesi, wajah,
     * perangkat, serta akun murid dan petugas). Akun Administrator dan jadwal waktu
     * solat tetap dipertahankan.
     */
    public function clearAll(Request $request): RedirectResponse
    {
        $request->validate([
            'confirm_text' => ['required', 'string', 'in:HAPUS SEMUA'],
        ], [
            'confirm_text.required' => 'Ketik HAPUS SEMUA pada kolom konfirmasi terlebih dahulu.',
            'confirm_text.in' => 'Teks konfirmasi harus persis bertuliskan HAPUS SEMUA (huruf kapital).',
        ]);

        Storage::disk(SolatProofService::Disk)->deleteDirectory(SolatProofService::Folder);
        Storage::disk('local')->deleteDirectory('face-photos');
        Storage::disk('local')->deleteDirectory(FacePhotoQueueService::Folder);

        DB::transaction(function (): void {
            SolatAttendanceLog::query()->delete();
            SolatSession::query()->delete();
            FacePhotoUpload::query()->delete();
            FaceEmbedding::query()->delete();
            Device::query()->delete();
            User::query()->where('role', '!=', User::RoleAdmin)->delete();

            User::query()->where('role', User::RoleAdmin)->update([
                'enrollment_status' => User::EnrollmentNotStarted,
                'enrolled_at' => null,
            ]);
        });

        return back()->with(
            'status',
            'Seluruh data operasional (riwayat absen, sesi, wajah, perangkat, serta akun murid dan petugas) telah dihapus bersih.',
        );
    }
}
