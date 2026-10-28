<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sandi bawaan (nomor induk untuk murid, solat123 untuk petugas) hanya boleh
 * dipakai sekali. Selama belum diganti, anggota diarahkan ke halaman ganti
 * sandi supaya akunnya tidak dipakai orang lain.
 */
class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->isKiosk() || $user->password_changed_at !== null) {
            return $next($request);
        }

        if ($request->routeIs('password.*', 'logout', 'login.*')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'password_change_required',
                'message' => 'Sandi Anda masih sandi bawaan. Ganti dulu sebelum memakai halaman lain.',
            ], 403);
        }

        return redirect()
            ->route('password.edit')
            ->with('warning', 'Sandi Anda masih sandi bawaan. Ganti dulu sebelum memakai halaman lain.');
    }
}
