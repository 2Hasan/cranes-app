<?php

use App\Http\Controllers\CranePlateController;
use App\Http\Controllers\CraneReportController;
use App\Http\Controllers\PushSubscriptionController;
use Illuminate\Support\Facades\Route;

// The app is internal: visitors go to the login page, signed-in users to the dashboard.
Route::get('/', fn () => redirect('/admin'));

// Guests are sent to the Filament login page instead of the default /login route.
Route::get('/login', fn () => redirect('/admin/login'))->name('login');

Route::get('/cranes/{crane}/report', CraneReportController::class)
    ->middleware('auth')
    ->name('cranes.report');

Route::middleware('auth')->prefix('push')->name('push.')->group(function () {
    Route::post('/subscribe', [PushSubscriptionController::class, 'store'])->name('subscribe');
    Route::post('/unsubscribe', [PushSubscriptionController::class, 'destroy'])->name('unsubscribe');
});

Route::get('/cranes/{code}/plate.svg', CranePlateController::class)
    ->middleware('auth')
    ->where('code', '[^/]+')
    ->name('cranes.plate');
