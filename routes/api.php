<?php

declare(strict_types=1);

use Salvon\Facade\Route;

Route::middleware('require_mfa')->group(function () {
    Route::loadApiDir(__DIR__);
});

Route::api(function () {
    Route::files(__DIR__ . '/auth.php');
});
