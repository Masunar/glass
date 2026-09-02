<?php

declare(strict_types=1);

use Salvon\Facade\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RegonController;
use App\Http\Controllers\PriceListController;
use App\Http\Controllers\GlobalParameterController;

Route::post('/regon/find-by-nip', [RegonController::class, 'findByNip'])->name('regon.find-by-nip');
Route::crudController(UserController::class, callback: function () {
    Route::get('roles', [UserController::class, 'roles'])->name('roles');
});

Route::prefix('/parameters')->name('parameters_')->group(static function (): void {
    Route::get('/', [GlobalParameterController::class, 'list'])->name('list');
    Route::put('/', [GlobalParameterController::class, 'update'])->name('update');
});

Route::prefix('/price-list')->name('price_list_')->group(static function (): void {
    Route::get('/', [PriceListController::class, 'matrix'])->name('matrix');
    Route::put('/', [PriceListController::class, 'update'])->name('update');
});
