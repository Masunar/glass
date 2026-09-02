<?php

use Salvon\Facade\Route;

Route::get('/', function () {
    abort(404);
});

Route::any('/login', function () {
    abort(403);
})->name('login');
