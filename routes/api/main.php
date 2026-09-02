<?php

declare(strict_types=1);

use Salvon\Facade\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RegonController;

Route::post('/regon/find-by-nip', [RegonController::class, 'findByNip'])->name('regon.find-by-nip');
Route::crudController(UserController::class, callback: function () {
    Route::get('roles', [UserController::class, 'roles'])->name('roles');
});

