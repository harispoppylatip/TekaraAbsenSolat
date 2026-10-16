<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credential = $request->loginCredential();
        $password = (string) $request->input('password');

        $user = User::query()
            ->where(function ($query) use ($credential) {
                $query->whereRaw('LOWER(email) = ?', [mb_strtolower($credential)])
                    ->orWhere('identifier_number', $credential)
                    ->orWhere('email', $credential);
            })
            ->first();

        if (! $user || ! Hash::check($password, (string) $user->password)) {
            $errorKey = $request->filled('login') ? 'login' : 'email';

            return back()
                ->withInput($request->only('login', 'email'))
                ->withErrors([$errorKey => 'NISN atau Email atau sandi tidak cocok.']);
        }

        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()
            ->intended(route($user->homeRoute()))
            ->with('status', 'Selamat datang, '.$user->name.'.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('status', 'Anda sudah keluar dari aplikasi.');
    }
}
