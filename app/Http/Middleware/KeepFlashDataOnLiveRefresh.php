<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menjaga pesan sekali-tampil saat halaman menyegarkan wilayahnya sendiri.
 *
 * Wilayah yang diperbarui berkala memanggil ulang alamat halaman yang sama.
 * Permintaan itu ikut memakan pesan simpan milik pengguna, sehingga pesannya
 * hilang sebelum sempat dibaca. Permintaan penyegaran ditandai lewat header
 * X-Live-Refresh, dan pesannya dikembalikan agar tetap tampil.
 */
class KeepFlashDataOnLiveRefresh
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->headers->has('X-Live-Refresh') && $request->hasSession()) {
            $request->session()->reflash();
        }

        return $response;
    }
}
