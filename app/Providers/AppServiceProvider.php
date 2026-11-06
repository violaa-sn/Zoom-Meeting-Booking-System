<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
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
        // Dipakai route: middleware('can:admin'). klo g ad ini, SEMUA user (termasuk admin) kena 403
        // di menu Persetujuan, Akun Zoom, Pengguna, dan Departemen.
        Gate::define('admin', fn (User $user) => $user->isAdmin());

        // Kalau APP_URL di .env memakai https://, semua link/form/aset dipaksa https
        // (mencegah halaman "campur" http dan https saat di hosting).
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
