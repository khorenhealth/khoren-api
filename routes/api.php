<?php

use App\Http\Controllers\Api\CurrentUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:10,1')->group(function () {
    Route::post('/sign-up', [RegisteredUserController::class, 'store'])
        ->name('api.sign-up');

    Route::post('/sign-in', [AuthenticatedSessionController::class, 'store'])
        ->name('api.sign-in');
});

Route::middleware('throttle:email-verification')->group(function () {
    Route::post('/email/verify', [EmailVerificationController::class, 'verify'])
        ->name('api.email.verify');

    Route::post('/email/resend', [EmailVerificationController::class, 'resend'])
        ->name('api.email.resend');

    Route::post('/email/change', [EmailVerificationController::class, 'change'])
        ->name('api.email.change');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware(['auth:sanctum', 'throttle:60,1'])
    ->name('api.logout');

Route::get('/user', CurrentUserController::class)
    ->middleware('auth:sanctum');
