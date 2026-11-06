<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\NotifyExpiredBookings;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Cek status user di setiap request web: user yang dinonaktifkan admin langsung dikeluarkan
        $middleware->appendToGroup('web', EnsureUserIsActive::class);
        // Pengajuan pending yang sudah lewat tanggal: peminjam diberi notifikasi (maks. sekali per menit)
        $middleware->appendToGroup('web', NotifyExpiredBookings::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
