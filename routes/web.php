<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfilePasswordController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ZoomAccountController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/kalender');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::put('/profil/password', [ProfilePasswordController::class, 'update'])->name('profile.password');

    Route::get('/kalender', [BookingController::class, 'calendar'])->name('booking.calendar');
    Route::get('/booking', [BookingController::class, 'index'])->name('booking.index');
    Route::get('/booking/ajukan', [BookingController::class, 'create'])->name('booking.create');
    Route::get('/booking/rekomendasi', [BookingController::class, 'recommend'])->name('booking.recommend')->middleware('throttle:30,1');
    Route::post('/booking', [BookingController::class, 'store'])->name('booking.store')->middleware('throttle:20,1');
    Route::get('/booking/{booking}/tanggal', [BookingController::class, 'occurrences'])->name('booking.occurrences');
    Route::post('/booking/tanggal/{occurrence}/batal', [BookingController::class, 'cancelOccurrence'])->name('booking.occurrences.cancel');
    Route::post('/booking/{booking}/batal', [BookingController::class, 'cancel'])->name('booking.cancel');

    Route::post('/notifikasi/baca-semua', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifikasi/{notification}', [NotificationController::class, 'read'])->name('notifications.read');

    Route::middleware('can:admin')->group(function () {
        Route::get('/persetujuan', [ApprovalController::class, 'index'])->name('approvals.index');
        Route::get('/persetujuan/{booking}/rekomendasi', [ApprovalController::class, 'recommend'])->name('approvals.recommend');
        Route::post('/persetujuan/{booking}/setujui', [ApprovalController::class, 'approve'])->name('approvals.approve');
        Route::post('/persetujuan/{booking}/tolak', [ApprovalController::class, 'reject'])->name('approvals.reject');

        Route::resource('departments', DepartmentController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('zoom-accounts', ZoomAccountController::class)->only(['index', 'store', 'update', 'destroy']);
    });
});
