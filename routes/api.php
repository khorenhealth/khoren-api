<?php

use App\Http\Controllers\Api\CurrentUserController;
use App\Http\Controllers\Api\EmergencyContactController;
use App\Http\Controllers\Api\HomeVisitAddressController;
use App\Http\Controllers\Api\MedicalInformationController;
use App\Http\Controllers\Api\MedicationController;
use App\Http\Controllers\Api\NotificationPreferenceController;
use App\Http\Controllers\Api\PrivacyConsentController;
use App\Http\Controllers\Api\ProfileCompletionController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProfilePhotoController;
use App\Http\Controllers\Api\SupportingRecordController;
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

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])
        ->name('api.profile.show');

    Route::match(['post', 'put', 'patch'], '/profile', [ProfileController::class, 'update'])
        ->name('api.profile.update');

    Route::match(['post', 'put', 'patch'], '/profile/home-visit', [HomeVisitAddressController::class, 'update'])
        ->name('api.profile.home-visit.update');

    Route::match(['post', 'put', 'patch'], '/profile/medical-information', [MedicalInformationController::class, 'update'])
        ->name('api.profile.medical-information.update');

    Route::match(['post', 'put', 'patch'], '/profile/medications', [MedicationController::class, 'update'])
        ->name('api.profile.medications.update');

    Route::match(['post', 'put', 'patch'], '/profile/emergency-contact', [EmergencyContactController::class, 'update'])
        ->name('api.profile.emergency-contact.update');

    Route::match(['post', 'put', 'patch'], '/profile/notifications', [NotificationPreferenceController::class, 'update'])
        ->name('api.profile.notifications.update');

    Route::match(['post', 'put', 'patch'], '/profile/privacy', [PrivacyConsentController::class, 'update'])
        ->name('api.profile.privacy.update');

    Route::match(['post', 'put', 'patch'], '/profile/completion', [ProfileCompletionController::class, 'update'])
        ->name('api.profile.completion.update');

    Route::post('/profile/records', [SupportingRecordController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('api.profile.records.store');

    Route::get('/profile/records/{supportingRecord}', [SupportingRecordController::class, 'show'])
        ->name('api.profile.records.show');

    Route::delete('/profile/records/{supportingRecord}', [SupportingRecordController::class, 'destroy'])
        ->name('api.profile.records.destroy');

    Route::post('/profile/photo', [ProfilePhotoController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('api.profile.photo.store');

    Route::delete('/profile/photo', [ProfilePhotoController::class, 'destroy'])
        ->name('api.profile.photo.destroy');
});
