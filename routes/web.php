<?php

use App\Http\Controllers\CraneReportController;
use App\Http\Controllers\PushSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Guests are sent to the Filament login page instead of the default /login route.
Route::get('/login', fn () => redirect('/admin/login'))->name('login');

Route::get('/cranes/{crane}/report', CraneReportController::class)
    ->middleware('auth')
    ->name('cranes.report');

Route::middleware('auth')->prefix('push')->name('push.')->group(function () {
    Route::post('/subscribe', [PushSubscriptionController::class, 'store'])->name('subscribe');
    Route::post('/unsubscribe', [PushSubscriptionController::class, 'destroy'])->name('unsubscribe');
});
