<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AccountController;
use App\Http\Controllers\Auth\AuthenticationController;
use App\Http\Controllers\Auth\MfaController;
use Salvon\Facade\Route;

Route::groupPrefix('auth', static function () {
    Route::post('/login', [AuthenticationController::class, 'login'])->name('login');
    Route::any('/logout', [AuthenticationController::class, 'logout'])->name('logout');
    Route::post('/forgot-password', [AuthenticationController::class, 'forgotPassword'])->name('forgotPassword');
    Route::post('/reset-password', [AuthenticationController::class, 'resetPassword'])->name('resetPassword');
    Route::middleware('require_mfa')->group(function () {
        Route::sanctum(function () {
            Route::get('/user', [AuthenticationController::class, 'user'])->name('user');
            Route::post('/change-password', [AccountController::class, 'changePassword'])->name('changePassword');
            Route::post('/update-profile', [AccountController::class, 'updateProfile'])->name('updateProfile');
        });
    });
});

Route::groupPrefix('mfa', static function () {
    Route::get('/requirement', [MfaController::class, 'requirement'])->name('requirement');
    Route::post('/verify', [MfaController::class, 'verify'])->name('verify');
    Route::post('/recovery-account', [MfaController::class, 'recoveryAccount'])->name('recoveryAccount');
    Route::post('/begin-setup', [MfaController::class, 'beginSetup'])->name('beginSetup');
    Route::post('/finish-setup', [MfaController::class, 'finishSetup'])->name('finishSetup');
    Route::get('/recovery-key', [MfaController::class, 'recoveryKey'])->name('recoveryKey');
    Route::post('/disable', [MfaController::class, 'disable'])->name('disable');
    Route::post('/begin-email-setup', [MfaController::class, 'beginEmailSetup'])->name('beginEmailSetup');
    Route::post('/finish-email-setup', [MfaController::class, 'finishEmailSetup'])->name('finishEmailSetup');
});
