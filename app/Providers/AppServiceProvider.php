<?php

namespace App\Providers;

use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Nama hari dan bulan ditulis dalam bahasa Indonesia, misalnya
        // "Senin, 9 Oktober 2026", bukan "Monday, October 9, 2026".
        Carbon::setLocale(config('app.locale'));
    }
}
