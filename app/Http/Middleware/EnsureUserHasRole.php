<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi halaman menurut peran pengguna.
 *
 * Peran yang ditolak dikembalikan ke halaman awalnya sendiri, bukan ke halaman
 * masuk, supaya anggota yang salah tekan tidak dikeluarkan dari sesinya.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Belum masuk ke aplikasi.'], 401)
                : redirect()->route('login');
        }

        if ($roles === [] || in_array($user->role, $roles, true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Akun ini tidak boleh membuka halaman tersebut.',
                'display_message' => 'Tidak diizinkan',
                'display_detail' => 'Lapor operator',
            ], 403);
        }

        $names = array_map(
            static fn (string $role): string => (string) (config('solat.roles')[$role] ?? $role),
            $roles,
        );

        return redirect()
            ->route($user->homeRoute())
            ->with('error', 'Halaman itu hanya untuk '.implode(' atau ', $names).'.');
    }
}
