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
use App\Services\SystemBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

/**
 * Halaman pengaturan, cadangan (backup & restore), dan pembersihan data sistem khusus Administrator.
 *
 * Setiap aksi penghapusan membersihkan baik baris basis data maupun berkas
 * fisik di cakram privat (foto bukti absen, foto wajah, dan antrean unggahan)
 * supaya tidak menyisakan berkas yatim di penyimpanan.
 */
class SettingController extends Controller
{
    public function index(SystemBackupService $backup): View
    {
        $storageSummary = $backup->storageSummary();

        return view('settings.index', [
            'attendanceCount' => SolatAttendanceLog::query()->count(),
            'sessionCount' => SolatSession::query()->count(),
            'faceCount' => FaceEmbedding::query()->count(),
            'queueCount' => FacePhotoUpload::query()->count(),
            'memberCount' => User::query()->where('role', '!=', User::RoleAdmin)->count(),
            'adminCount' => User::query()->where('role', User::RoleAdmin)->count(),
            'deviceCount' => Device::query()->count(),
            'photoFileCount' => $storageSummary['file_count'],
            'photoTotalBytes' => $storageSummary['total_bytes'],
        ]);
    }

    /**
     * Unduh cadangan lengkap (.zip) berisi seluruh tabel database dan seluruh folder foto anak-anak.
     */
    public function downloadBackup(SystemBackupService $backup): Response
    {
        $zipBinary = $backup->createBackupZipBinary();
        $filename = 'backup-absen-solat-'.now()->format('Y-m-d-His').'.zip';

        return response($zipBinary, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($zipBinary),
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * Pulihkan seluruh basis data dan berkas foto dari berkas .zip cadangan yang diunggah.
     */
    public function restoreBackup(Request $request, SystemBackupService $backup): RedirectResponse
    {
        $request->validate([
            'backup_file' => ['required', 'file', 'mimes:zip', 'max:512000'],
            'confirm_restore' => ['accepted'],
        ], [
            'backup_file.required' => 'Pilih berkas cadangan (.zip) dari komputer atau HP Anda terlebih dahulu.',
            'backup_file.file' => 'Berkas cadangan tidak valid.',
            'backup_file.mimes' => 'Berkas cadangan harus berformat .zip hasil unduhan dari menu Backup.',
            'backup_file.max' => 'Ukuran berkas cadangan melebihi batas maksimum (500 MB).',
            'confirm_restore.accepted' => 'Centang kotak persetujuan sebelum memulihkan data.',
        ]);

        $uploadedFile = $request->file('backup_file');
        $zipBytes = (string) file_get_contents($uploadedFile->getRealPath());

        try {
            $result = $backup->restoreFromZipBinary($zipBytes, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        Auth::login($result['admin_user']);
        $request->session()->regenerate();

        $tables = $result['tables'];
        $totalUsers = $tables['users'] ?? 0;
        $totalLogs = $tables['solat_attendance_logs'] ?? 0;
        $totalFaces = $tables['face_embeddings'] ?? 0;
        $totalPhotos = $result['photos'];

        return redirect()
            ->route('settings.index')
            ->with(
                'status',
                "Pemulihan data berhasil! {$totalUsers} akun pengguna, {$totalFaces} data sidik wajah, {$totalLogs} riwayat kehadiran, dan {$totalPhotos} berkas foto telah dikembalikan seperti semula.",
            );
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
