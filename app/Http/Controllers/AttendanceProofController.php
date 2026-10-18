<?php

namespace App\Http\Controllers;

use App\Models\SolatAttendanceLog;
use App\Services\SolatProofService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Membuka foto bukti absen.
 *
 * Berkasnya berada di cakram privat, jadi hanya bisa keluar lewat pintu ini:
 * admin, petugas, dan anggota yang bersangkutan. Anggota lain tidak boleh
 * melihat foto absen orang lain meski tahu alamatnya.
 */
class AttendanceProofController extends Controller
{
    public function __invoke(Request $request, SolatAttendanceLog $log, SolatProofService $proofs): StreamedResponse
    {
        $user = $request->user();

        $allowed = $user !== null
            && ($user->isAdmin() || $user->isPetugas() || $user->getKey() === $log->user_id);

        abort_unless($allowed, 403);

        $path = $proofs->path($log);

        abort_if($path === null || ! $proofs->exists($path), 404);

        $cacheControl = $request->filled('v')
            ? 'private, max-age=86400, must-revalidate'
            : 'private, no-cache, must-revalidate';

        return Storage::disk(SolatProofService::Disk)->response($path, 'bukti-absen-'.$log->getKey().'.jpg', [
            'Cache-Control' => $cacheControl,
        ]);
    }
}
