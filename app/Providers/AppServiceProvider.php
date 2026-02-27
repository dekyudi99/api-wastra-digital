<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Midtrans\Config;
use App\Models\User;
use App\Observers\UserObserver;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Gate;

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
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized = true;
        Config::$is3ds = true;
        User::observe(UserObserver::class);
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
        Gate::define('viewPulse', function (User $user) {
            // // Opsi A: Berdasarkan email admin tertentu
            // return $user->email === 'yudiartana226@gmail.com'; 

            // Opsi B: Berdasarkan role (jika Anda punya kolom 'role' di tabel users)
            return $user->role === 'admin';
        });
    }
}
