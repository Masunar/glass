<?php

declare(strict_types=1);

use Salvon\Facade\Route;
use Salvon\Bundle\Media\Controller\MediaController;

Route::middleware('web')->prefix('/public')->group(static function (): void {
    Route::groupPrefix('media', static function (): void {
        Route::get('/{uuid}', [MediaController::class, 'getMedia'])->name('get-media');
        Route::get('/{uuid}/{variant}', [MediaController::class, 'getMedia'])->name('get-media-variant');
    });

    Route::groupPrefix('download-media', static function (): void {
        Route::get('/{uuid}', [MediaController::class, 'download'])->name('download-media');
        Route::get('/{uuid}/{variant}', [MediaController::class, 'download'])->name('download-media-variant');
    });
});
