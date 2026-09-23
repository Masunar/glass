<?php

declare(strict_types=1);

use Salvon\Facade\Route;

// `require_module` po `require_mfa`: dostep do modulu (U-04) jest
// pietrem nad uprawnieniem do zasobu, ale pod uwierzytelnieniem.
Route::middleware(['require_mfa', 'require_module'])->group(function () {
    Route::loadApiDir(__DIR__);
});

Route::api(function () {
    Route::files(__DIR__ . '/auth.php');
});
