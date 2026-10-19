<?php

use App\Http\Controllers\Api\FaceApiController;
use App\Http\Controllers\Api\FingerprintApiController;
use App\Http\Controllers\Api\SolatScanController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceProofController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\FaceDataController;
use App\Http\Controllers\FingerprintDataController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\Murid\HistoryController;
use App\Http\Controllers\PrayerTimeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SolatSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route(auth()->user()->homeRoute());
    }

    return redirect()->route('login');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/masuk', [LoginController::class, 'create'])->name('login');
    Route::post('/masuk', [LoginController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.store');
});

Route::post('/keluar', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/ganti-sandi', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/ganti-sandi', [PasswordController::class, 'update'])->name('password.update');
    Route::put('/profil/email', [PasswordController::class, 'updateEmail'])->name('profile.email');

    Route::get('/ringkasan', DashboardController::class)
        ->middleware('role:admin,petugas')
        ->name('dashboard');

    Route::get('/riwayat', HistoryController::class)->name('murid.riwayat');

    /*
    | Pendaftaran wajah mandiri.
    |
    | Dibuka untuk SEMUA peran yang sudah masuk, termasuk admin dan petugas,
    | sebab semua orang absen di depan kamera dan guru baru juga perlu
    | mendaftarkan wajahnya sendiri. Identitas foto selalu diambil dari akun yang
    | sedang masuk, jadi tidak ada nomor akun yang dibaca dari peramban.
    */
    Route::get('/pendaftaran-wajah', [EnrollmentController::class, 'show'])->name('enrollment.show');
    Route::get('/pendaftaran-wajah/proses', [EnrollmentController::class, 'progress'])->name('enrollment.progress');
    Route::post('/pendaftaran-wajah/foto', [EnrollmentController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('enrollment.photos.store');
    Route::get('/pendaftaran-wajah/foto/{faceEmbedding}', [EnrollmentController::class, 'showPhoto'])->name('enrollment.photos.show');
    Route::delete('/pendaftaran-wajah/foto/{faceEmbedding}', [EnrollmentController::class, 'destroyPhoto'])->name('enrollment.photos.destroy');
    Route::delete('/pendaftaran-wajah/foto', [EnrollmentController::class, 'destroyAll'])->name('enrollment.photos.clear');
    Route::post('/pendaftaran-wajah/proses/{batch}/ulang', [EnrollmentController::class, 'retry'])->name('enrollment.retry');
    Route::post('/pendaftaran-wajah/selesai', [EnrollmentController::class, 'complete'])->name('enrollment.complete');
    Route::get('/pendaftaran-wajah/selesai', [EnrollmentController::class, 'done'])->name('enrollment.done');

    // Foto bukti absen disajikan lewat rute berizin, bukan berkas publik.
    Route::get('/bukti-absen/{log}', AttendanceProofController::class)->name('attendance.proof');

    Route::middleware('camera')->group(function () {
        Route::get('/absen-wajah', [AttendanceController::class, 'index'])->name('attendance.wajah');
        Route::post('/absen-wajah/pindai', [AttendanceController::class, 'scan'])->name('attendance.wajah.scan');
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/anggota', [MemberController::class, 'index'])->name('members.index');
        Route::get('/anggota/impor', [MemberController::class, 'importForm'])->name('members.import');
        Route::post('/anggota/impor', [MemberController::class, 'import'])->name('members.import.store');
        Route::get('/anggota/impor/template', [MemberController::class, 'downloadTemplate'])->name('members.import.template');
        Route::get('/anggota/tambah', [MemberController::class, 'create'])->name('members.create');
        Route::post('/anggota', [MemberController::class, 'store'])->name('members.store');
        Route::get('/anggota/{member}/ubah', [MemberController::class, 'edit'])->name('members.edit');
        Route::put('/anggota/{member}', [MemberController::class, 'update'])->name('members.update');
        Route::post('/anggota/{member}/sandi', [MemberController::class, 'resetPassword'])->name('members.password');
        Route::delete('/anggota/{member}', [MemberController::class, 'destroy'])->name('members.destroy');

        Route::get('/waktu-solat', [PrayerTimeController::class, 'index'])->name('prayer-times.index');
        Route::post('/waktu-solat', [PrayerTimeController::class, 'store'])->name('prayer-times.store');
        Route::put('/waktu-solat/hari-aktif', [PrayerTimeController::class, 'updateDays'])->name('prayer-times.days.update');
        Route::put('/waktu-solat/{prayerTime}', [PrayerTimeController::class, 'update'])->name('prayer-times.update');
        Route::delete('/waktu-solat/{prayerTime}', [PrayerTimeController::class, 'destroy'])->name('prayer-times.destroy');

        Route::get('/wajah', [FaceDataController::class, 'index'])->name('face.index');
        Route::get('/wajah/proses', [FaceDataController::class, 'progress'])->name('face.progress');
        Route::post('/wajah/proses/{batch}/ulang', [FaceDataController::class, 'retry'])->name('face.queue.retry');
        Route::post('/wajah/unggah', [FaceDataController::class, 'store'])->name('face.photos.store');
        Route::delete('/wajah/foto/{faceEmbedding}', [FaceDataController::class, 'destroyPhoto'])->name('face.photos.destroy');
        Route::delete('/wajah/anggota/{member}', [FaceDataController::class, 'destroyMember'])->name('face.member.destroy');
        Route::put('/wajah/anggota/{member}/daftar-ulang', [FaceDataController::class, 'requestReenrollment'])->name('face.enrollment.reopen');
        Route::put('/wajah/petugas/{member}', [FaceDataController::class, 'updateOperator'])->name('face.operator.update');

        Route::get('/sidik-jari', [FingerprintDataController::class, 'index'])->name('fingerprint.index');
        Route::post('/sidik-jari/daftar', [FingerprintDataController::class, 'enroll'])->name('fingerprint.enroll');
        Route::post('/sidik-jari/anggota/{member}/ulang', [FingerprintDataController::class, 'replace'])->name('fingerprint.replace');
        Route::delete('/sidik-jari/perintah', [FingerprintDataController::class, 'cancelEnroll'])->name('fingerprint.command.cancel');
        Route::delete('/sidik-jari/anggota/{member}', [FingerprintDataController::class, 'destroyMember'])->name('fingerprint.member.destroy');

        Route::get('/perangkat', [DeviceController::class, 'index'])->name('devices.index');
        Route::put('/perangkat/{device}', [DeviceController::class, 'update'])->name('devices.update');
        Route::put('/perangkat/{device}/daftar', [DeviceController::class, 'register'])->name('devices.register');
        Route::put('/perangkat/{device}/status', [DeviceController::class, 'status'])->name('devices.status');
        Route::delete('/perangkat/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');

        Route::get('/pengaturan', [SettingController::class, 'index'])->name('settings.index');
        Route::get('/pengaturan/backup', [SettingController::class, 'downloadBackup'])->name('settings.backup.download');
        Route::post('/pengaturan/backup/pulihkan', [SettingController::class, 'restoreBackup'])->name('settings.backup.restore');
        Route::delete('/pengaturan/riwayat-absen', [SettingController::class, 'clearAttendance'])->name('settings.clear-attendance');
        Route::delete('/pengaturan/sesi-absen', [SettingController::class, 'clearSessions'])->name('settings.clear-sessions');
        Route::delete('/pengaturan/data-wajah', [SettingController::class, 'clearFaces'])->name('settings.clear-faces');
        Route::delete('/pengaturan/anggota', [SettingController::class, 'clearMembers'])->name('settings.clear-members');
        Route::delete('/pengaturan/perangkat', [SettingController::class, 'clearDevices'])->name('settings.clear-devices');
        Route::delete('/pengaturan/semua-data', [SettingController::class, 'clearAll'])->name('settings.clear-all');
    });

    Route::middleware('role:admin,petugas')->group(function () {
        Route::get('/sesi-absen', [SolatSessionController::class, 'index'])->name('sessions.index');
        Route::post('/sesi-absen/buat', [SolatSessionController::class, 'generate'])->name('sessions.generate');
        Route::put('/sesi-absen/{solatSession}/buka', [SolatSessionController::class, 'open'])->name('sessions.open');
        Route::put('/sesi-absen/{solatSession}/tutup', [SolatSessionController::class, 'close'])->name('sessions.close');

        Route::get('/rekap', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/rekap/unduh', [ReportController::class, 'export'])->name('reports.export');
    });

    /*
    | Merapikan sesi yang salah dibuat dan mengurus mode uji coba hanya boleh
    | dilakukan admin, karena keduanya menghapus catatan kehadiran yang sudah
    | masuk dan menyalakan absen percobaan.
    */
    Route::prefix('sesi-absen')->middleware('role:admin')->group(function () {
        Route::delete('/hapus-terpilih', [SolatSessionController::class, 'destroySelected'])->name('sessions.destroy-selected');
        Route::delete('/hapus-tanggal', [SolatSessionController::class, 'destroyDay'])->name('sessions.destroy-day');
        Route::delete('/{solatSession}', [SolatSessionController::class, 'destroy'])
            ->whereNumber('solatSession')
            ->name('sessions.destroy');

        Route::post('/uji/nyalakan', [SolatSessionController::class, 'startTest'])->name('sessions.test.start');
        Route::put('/uji/matikan', [SolatSessionController::class, 'stopTest'])->name('sessions.test.stop');
        Route::delete('/uji/hapus', [SolatSessionController::class, 'destroyTest'])->name('sessions.test.destroy');
    });
});

/*
| Layanan untuk perangkat. Alat tidak memakai akun, jadi pengenalnya adalah
| kode perangkat yang sudah didaftarkan di halaman Perangkat.
*/
Route::prefix('api')->middleware('throttle:60,1')->group(function () {
    Route::post('/solat/scan', [SolatScanController::class, 'scan'])->name('api.solat.scan');
    Route::get('/solat/sesi', [SolatScanController::class, 'session'])->name('api.solat.session');
    Route::post('/wajah/daftar', [FaceApiController::class, 'register'])->name('api.face.register');
    Route::get('/wajah/angka', [FaceApiController::class, 'embeddings'])->name('api.face.embeddings');

    /*
    | Layanan sensor sidik jari ESP32 (HTTP REST API).
    */
    Route::prefix('fingerprint')->group(function () {
        Route::get('/users', [FingerprintApiController::class, 'users'])->name('api.fingerprint.users');
        Route::get('/template/{userId}', [FingerprintApiController::class, 'template'])->name('api.fingerprint.template');
        Route::get('/templates', [FingerprintApiController::class, 'templates'])->name('api.fingerprint.templates');
        Route::get('/command', [FingerprintApiController::class, 'command'])->name('api.fingerprint.command');
        Route::post('/register', [FingerprintApiController::class, 'register'])->name('api.fingerprint.register');
        Route::post('/match', [FingerprintApiController::class, 'match'])->name('api.fingerprint.match');
    });
});
