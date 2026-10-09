<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hanya petugas yang ditugaskan admin yang boleh memakai kamera absen.
 *
 * Penugasan itu ditandai dengan `can_scan_face` pada data anggota, supaya admin
 * bisa memindahkan tugas kamera tanpa mengubah peran orangnya.
 */
class EnsureUserIsCameraOperator
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Belum masuk ke aplikasi.'], 401)
                : redirect()->route('login');
        }

        if ($user->isAdmin() || $user->isCameraOperator()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Akun ini belum ditugaskan memegang kamera absen. Minta admin menugaskan di halaman Data Wajah.',
                'display_message' => 'Bukan petugas',
                'display_detail' => 'Lapor operator',
            ], 403);
        }

        return redirect()
            ->route($user->homeRoute())
            ->with('error', 'Kamera absen hanya bisa dipakai petugas yang ditugaskan admin di halaman Data Wajah.');
    }
}
