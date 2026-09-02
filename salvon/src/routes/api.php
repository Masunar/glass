<?php

declare(strict_types=1);

use Salvon\Facade\Route;
use Salvon\Controller\CountryController;

Route::api(static function () {
    Route::groupPrefix('country', static function () {
        Route::get('/', [CountryController::class, 'countries'])->name('countries');
        Route::get('/{iso}', [CountryController::class, 'country'])->name('country');
    });
});
