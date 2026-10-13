<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('auth.password');
    }

    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! Hash::check((string) $request->validated('current_password'), (string) $user->password)) {
            return back()->withErrors(['current_password' => 'Sandi saat ini tidak cocok.']);
        }

        $user->forceFill([
            'password' => Hash::make((string) $request->validated('password')),
            'password_changed_at' => now(),
        ])->save();

        // Setelah sandi diganti, pemilik akun yang wajahnya belum terdaftar
        // langsung diarahkan mendaftarkan wajahnya. Urutan ini disengaja: sandi
        // bawaan harus diganti lebih dulu supaya tidak ada wajah yang
        // didaftarkan dari akun yang masih bisa dibuka orang lain, dan pendaftaran
        // wajah tanpa absen langsung terasa manfaatnya karena halaman berikutnya
        // membuat pemiliknya bisa absen memakai wajah.
        if ($user->needsEnrollment()) {
            return redirect()
                ->route('enrollment.show')
                ->with('status', 'Sandi berhasil diganti. Daftarkan wajah Anda sekarang supaya bisa absen tanpa menempelkan jari.');
        }

        // Sisanya langsung dibawa ke halaman utamanya supaya tidak tertinggal di
        // halaman ganti sandi.
        return redirect()
            ->route($user->homeRoute())
            ->with('status', 'Sandi berhasil diganti. Pakai sandi baru pada masuk berikutnya.');
    }
}
