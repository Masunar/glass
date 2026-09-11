<?php

declare(strict_types=1);

use Salvon\Facade\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RegonController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\PriceListController;
use App\Http\Controllers\DictionaryController;
use App\Http\Controllers\ContractorController;
use App\Http\Controllers\ProductCatalogController;
use App\Http\Controllers\GlobalParameterController;

Route::get('/search', [SearchController::class, 'search'])->name('search');
Route::post('/regon/find-by-nip', [RegonController::class, 'findByNip'])->name('regon.find-by-nip');
Route::crudController(UserController::class, callback: function () {
    Route::get('roles', [UserController::class, 'roles'])->name('roles');
    Route::get('board', [UserController::class, 'board'])->name('board');
    Route::post('{entity}/invite', [UserController::class, 'invite'])->name('invite');
});

Route::prefix('/parameters')->name('parameters_')->group(static function (): void {
    Route::get('/', [GlobalParameterController::class, 'list'])->name('list');
    Route::put('/', [GlobalParameterController::class, 'update'])->name('update');
    Route::post('/preview', [GlobalParameterController::class, 'preview'])->name('preview');
    Route::get('/history', [GlobalParameterController::class, 'history'])->name('history');
});

Route::prefix('/price-list')->name('price_list_')->group(static function (): void {
    Route::get('/', [PriceListController::class, 'matrix'])->name('matrix');
    Route::put('/', [PriceListController::class, 'update'])->name('update');
});

Route::prefix('/catalog')->name('catalog_')->group(static function (): void {
    Route::post('/products', [ProductCatalogController::class, 'createProduct'])->name('product_create');
    Route::put('/products/{product}', [ProductCatalogController::class, 'updateProduct'])->name('product_update');
    Route::post('/groups', [ProductCatalogController::class, 'createGroup'])->name('group_create');
    Route::put('/groups/{group}', [ProductCatalogController::class, 'updateGroup'])->name('group_update');
});

Route::prefix('/contractors')->name('contractors_')->group(static function (): void {
    Route::get('/', [ContractorController::class, 'list'])->name('list');
    Route::post('/', [ContractorController::class, 'create'])->name('create');
    Route::get('/{contractor}', [ContractorController::class, 'card'])->name('card');
    Route::put('/{contractor}', [ContractorController::class, 'update'])->name('update');
    Route::put('/{contractor}/price-sections', [ContractorController::class, 'priceSections'])
        ->name('price_sections');
});

Route::prefix('/dictionaries')->name('dictionaries_')->group(static function (): void {
    Route::get('/', [DictionaryController::class, 'schema'])->name('schema');
    Route::get('/{slug}', [DictionaryController::class, 'rows'])->name('rows');
    Route::post('/{slug}', [DictionaryController::class, 'create'])->name('create');
    Route::put('/{slug}/{id}', [DictionaryController::class, 'update'])->name('update');
    Route::delete('/{slug}/{id}', [DictionaryController::class, 'deactivate'])->name('deactivate');
});

Route::prefix('/orders')->name('orders_')->group(static function (): void {
    Route::get('/', [OrderController::class, 'board'])->name('board');
    Route::post('/', [OrderController::class, 'create'])->name('create');
    // Przed trasa z parametrem, inaczej "form" zostanie wziete za numer.
    Route::get('/form', [OrderController::class, 'formOptions'])->name('form');
    Route::get('/{order}', [OrderController::class, 'card'])->name('card');
    Route::post('/{order}/transition', [OrderController::class, 'transition'])->name('transition');
    Route::get('/{order}/items', [OrderController::class, 'items'])->name('items');
    Route::post('/{order}/panes', [OrderController::class, 'savePane'])->name('pane_create');
    Route::put('/{order}/panes/{item}', [OrderController::class, 'savePane'])->name('pane_update');
    Route::post('/{order}/services', [OrderController::class, 'saveService'])->name('service_create');
    Route::put('/{order}/services/{item}', [OrderController::class, 'saveService'])->name('service_update');
    Route::delete('/{order}/items/{item}', [OrderController::class, 'deleteItem'])->name('item_delete');
    Route::put('/{order}/discounts', [OrderController::class, 'saveDiscounts'])->name('discounts');
    Route::get('/{order}/drawings', [OrderController::class, 'drawings'])->name('drawings');
    Route::post('/{order}/drawings', [OrderController::class, 'addDrawing'])->name('drawing_add');
    Route::get('/{order}/drawings/{drawing}', [OrderController::class, 'drawingFile'])->name('drawing_file');
    Route::delete('/{order}/drawings/{drawing}', [OrderController::class, 'deleteDrawing'])->name('drawing_delete');
    Route::put('/{order}/drawings-complete', [OrderController::class, 'declareDrawings'])->name('drawings_declare');
    Route::get('/{order}/payments', [OrderController::class, 'payments'])->name('payments');
    Route::post('/{order}/payments', [OrderController::class, 'addPayment'])->name('payment_add');
    // Korekta dopisuje wiersz, nie kasuje — stad POST.
    Route::post('/{order}/payments/{payment}/reverse', [OrderController::class, 'reversePayment'])->name('payment_reverse');
});
