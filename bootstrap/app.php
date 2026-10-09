<?php

use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsCameraOperator;
use App\Http\Middleware\KeepFlashDataOnLiveRefresh;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Wilayah halaman yang menyegarkan diri sendiri memakai ulang pesan
        // sekali-tampil, supaya pesan simpan tidak hilang sebelum sempat dibaca.
        // Pemeriksa sandi bawaan dipasang paling akhir supaya berlaku di semua
        // halaman tanpa perlu ditulis ulang di setiap rute.
        $middleware->web(append: [
            KeepFlashDataOnLiveRefresh::class,
            EnsurePasswordIsChanged::class,
        ]);

        // Perangkat tidak memegang sesi web, jadi permintaannya tidak diperiksa
        // token CSRF. Pengamannya adalah kode perangkat yang sudah didaftarkan.
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'camera' => EnsureUserIsCameraOperator::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
