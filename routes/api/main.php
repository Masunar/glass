<?php

declare(strict_types=1);

use Salvon\Facade\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\RegonController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\AccessController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\TemperingController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\PriceListController;
use App\Http\Controllers\DictionaryController;
use App\Http\Controllers\ContractorController;
use App\Http\Controllers\ProductCatalogController;
use App\Http\Controllers\GlobalParameterController;

// Pulpit bez uprawnienia: strona jest dostepna kazdemu zalogowanemu,
// a sekcje przycina usluga.
Route::get('/dashboard', [DashboardController::class, 'board'])->name('dashboard');
Route::get('/dashboard/alerts', [DashboardController::class, 'alerts'])->name('dashboard_alerts');
Route::get('/search', [SearchController::class, 'search'])->name('search');
// Wlasne ustawienia ekranu — bez uprawnienia, kazdy zmienia tylko swoje.
Route::put('/preferences', [PreferenceController::class, 'update'])->name('preferences');
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

Route::prefix('/alerts')->name('alerts_')->group(static function (): void {
    Route::get('/', [AlertController::class, 'board'])->name('board');
    Route::post('/', [AlertController::class, 'create'])->name('create');
    Route::put('/{rule}', [AlertController::class, 'update'])->name('update');
    Route::delete('/{rule}', [AlertController::class, 'delete'])->name('delete');
});

// Odhaczenie alertu stoi poza `/alerts`, bo to nie jest konfiguracja:
// chodzi na `orders.update`, a `/alerts` na uprawnieniu `alerts`.
Route::prefix('/alert-occurrences')->name('alert_occurrences_')->group(static function (): void {
    Route::post('/{occurrence}/acknowledge', [AlertController::class, 'acknowledge'])->name('ack');
    Route::delete('/{occurrence}/acknowledge', [AlertController::class, 'revoke'])->name('revoke');
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

Route::prefix('/warehouse')->name('warehouse_')->group(static function (): void {
    Route::get('/levels', [WarehouseController::class, 'levels'])->name('levels');
    Route::get('/demand', [WarehouseController::class, 'demand'])->name('demand');
    Route::put('/{product}/thresholds', [WarehouseController::class, 'thresholds'])->name('thresholds');
    Route::post('/{product}/count', [WarehouseController::class, 'count'])->name('count');

    // Rozjazd cennika stoi przed `/orders/{order}`, bo inaczej „price-drift"
    // wpadloby jako identyfikator zamowienia. Ta sama pulapka co przy
    // `/{order}/fittings/set`.
    Route::get('/price-drift', [PurchaseOrderController::class, 'drift'])->name('price_drift');
    Route::post('/price-drift/recalculate', [PurchaseOrderController::class, 'recalculate'])
        ->name('price_drift_recalculate');

    Route::prefix('/orders')->name('orders_')->group(static function (): void {
        Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');
        Route::post('/', [PurchaseOrderController::class, 'store'])->name('store');
        Route::post('/from-suggestions', [PurchaseOrderController::class, 'fromSuggestions'])
            ->name('from_suggestions');
        Route::get('/{order}', [PurchaseOrderController::class, 'show'])->name('show');
        Route::post('/{order}/items', [PurchaseOrderController::class, 'addItem'])->name('add_item');
        Route::delete('/{order}/items/{item}', [PurchaseOrderController::class, 'removeItem'])
            ->name('remove_item');
        Route::post('/{order}/send', [PurchaseOrderController::class, 'send'])->name('send');
        Route::post('/{order}/cancel', [PurchaseOrderController::class, 'cancel'])->name('cancel');
        Route::post('/{order}/receive', [PurchaseOrderController::class, 'receive'])->name('receive');
    });
});

Route::prefix('/tempering')->name('tempering_')->group(static function (): void {
    Route::get('/queue', [TemperingController::class, 'queue'])->name('queue');

    Route::prefix('/batches')->name('batches_')->group(static function (): void {
        Route::get('/', [TemperingController::class, 'batches'])->name('index');
        Route::post('/', [TemperingController::class, 'store'])->name('store');
        Route::get('/{batch}', [TemperingController::class, 'show'])->name('show');
        Route::put('/{batch}/plan', [TemperingController::class, 'plan'])->name('plan');
        Route::post('/{batch}/items', [TemperingController::class, 'addItems'])->name('add_items');
        Route::delete('/{batch}/items/{item}', [TemperingController::class, 'removeItem'])
            ->name('remove_item');
        Route::post('/{batch}/send', [TemperingController::class, 'send'])->name('send');
        Route::post('/{batch}/receive', [TemperingController::class, 'receive'])->name('receive');
        Route::post('/{batch}/settle', [TemperingController::class, 'settle'])->name('settle');
        Route::post('/{batch}/cancel', [TemperingController::class, 'cancel'])->name('cancel');
    });
});

Route::prefix('/production')->name('production_')->group(static function (): void {
    Route::get('/', [ProductionController::class, 'board'])->name('board');
    Route::post('/tasks/{task}/start', [ProductionController::class, 'start'])->name('task_start');
    Route::post('/tasks/{task}/finish', [ProductionController::class, 'finish'])->name('task_finish');
    Route::post('/tasks/{task}/issue', [ProductionController::class, 'issue'])->name('task_issue');
    // Cofniecie nie kasuje zadania, tylko wraca je do kolejki - stad POST.
    Route::post('/tasks/{task}/reopen', [ProductionController::class, 'reopen'])->name('task_reopen');
});

Route::get('/offers', [OfferController::class, 'index'])->name('offers_index');

Route::prefix('/access')->name('access_')->group(static function (): void {
    Route::get('/roles', [AccessController::class, 'roles'])->name('roles');
    Route::get('/roles/{role}', [AccessController::class, 'role'])->name('role');
    Route::put('/roles/{role}', [AccessController::class, 'saveRole'])->name('role_save');
    // Stala „packages" przed trasa z parametrem nie koliduje, bo
    // parametr jest liczba — ale kolejnosc zostaje dla czytelnosci.
    Route::get('/packages/new', [AccessController::class, 'package'])->name('package_new');
    Route::get('/packages/{package}', [AccessController::class, 'package'])->name('package');
    Route::post('/packages', [AccessController::class, 'savePackage'])->name('package_create');
    Route::put('/packages/{package}', [AccessController::class, 'savePackage'])->name('package_update');
    Route::delete('/packages/{package}', [AccessController::class, 'deletePackage'])->name('package_delete');
    // Odstepstwa przy uzytkowniku: nadania ponad role (U-05).
    Route::get('/users/{user}', [AccessController::class, 'user'])->name('user');
    Route::put('/users/{user}', [AccessController::class, 'saveUser'])->name('user_save');
});

Route::prefix('/orders')->name('orders_')->group(static function (): void {
    Route::get('/', [OrderController::class, 'board'])->name('board');
    Route::post('/', [OrderController::class, 'create'])->name('create');
    // Przed trasa z parametrem, inaczej "form" zostanie wziete za numer.
    Route::get('/form', [OrderController::class, 'formOptions'])->name('form');
    Route::get('/alerts', [OrderController::class, 'alerts'])->name('alerts');
    Route::get('/{order}', [OrderController::class, 'card'])->name('card');
    Route::post('/{order}/transition', [OrderController::class, 'transition'])->name('transition');
    Route::put('/{order}/deadline', [OrderController::class, 'saveDeadline'])->name('deadline');
    Route::put('/{order}/invoice', [OrderController::class, 'saveInvoice'])->name('invoice');
    Route::post('/{order}/credit-override', [OrderController::class, 'grantCreditOverride'])->name('credit_override');
    Route::delete('/{order}/credit-override', [OrderController::class, 'revokeCreditOverride'])
        ->name('credit_override_revoke');
    Route::put('/{order}/comments/{field}', [OrderController::class, 'saveComment'])
        ->whereIn('field', ['short', 'production', 'installer', 'offer'])
        ->name('comment');
    Route::get('/{order}/items', [OrderController::class, 'items'])->name('items');
    Route::post('/{order}/lists', [OrderController::class, 'saveList'])->name('list_create');
    Route::put('/{order}/lists/{list}', [OrderController::class, 'saveList'])->name('list_update');
    Route::delete('/{order}/lists/{list}', [OrderController::class, 'deleteList'])->name('list_delete');
    Route::put('/{order}/items/{item}/list', [OrderController::class, 'moveItem'])->name('item_move');
    // Podglad przed zapisem: POST, bo niesie caly formularz, ale nic
    // nie zmienia.
    Route::post('/{order}/panes/preview', [OrderController::class, 'previewPane'])->name('pane_preview');
    Route::post('/{order}/panes', [OrderController::class, 'savePane'])->name('pane_create');
    Route::put('/{order}/panes/{item}', [OrderController::class, 'savePane'])->name('pane_update');
    // Zestaw przed pojedyncza pozycja: '/{order}/fittings/set' nie moze
    // wpasc w '/{order}/fittings/{item}'.
    Route::post('/{order}/fittings/set', [OrderController::class, 'addFittingSet'])->name('fitting_set');
    Route::post('/{order}/fittings', [OrderController::class, 'saveFitting'])->name('fitting_create');
    Route::put('/{order}/fittings/{item}', [OrderController::class, 'saveFitting'])->name('fitting_update');

    Route::post('/{order}/services', [OrderController::class, 'saveService'])->name('service_create');
    Route::put('/{order}/services/{item}', [OrderController::class, 'saveService'])->name('service_update');
    Route::delete('/{order}/items/{item}', [OrderController::class, 'deleteItem'])->name('item_delete');
    Route::put('/{order}/discounts', [OrderController::class, 'saveDiscounts'])->name('discounts');
    Route::put('/{order}/investment', [OrderController::class, 'saveInvestment'])->name('investment');
    // Oferty zlecenia. Trasa szczegolowa `/offers/{offer}/...` idzie
    // po `/offers`, wiec stalych czlonow nie ma jak pomylic z id.
    Route::get('/{order}/offers', [OfferController::class, 'forOrder'])->name('offers');
    Route::post('/{order}/offers', [OfferController::class, 'issue'])->name('offer_issue');
    // Przed trasami z `{offer}`: „preview" to staly czlon, nie numer.
    Route::post('/{order}/offers/preview', [OfferController::class, 'preview'])->name('offer_preview');
    Route::get('/{order}/offers/{offer}/pdf', [OfferController::class, 'pdf'])->name('offer_pdf');
    Route::get('/{order}/offers/{offer}/mail', [OfferController::class, 'mailPreview'])->name('offer_mail');
    Route::post('/{order}/offers/{offer}/send', [OfferController::class, 'send'])->name('offer_send');
    Route::post('/{order}/offers/{offer}/sent', [OfferController::class, 'markSent'])->name('offer_sent');
    Route::post('/{order}/offers/{offer}/accept', [OfferController::class, 'accept'])->name('offer_accept');
    Route::post('/{order}/offers/{offer}/reject', [OfferController::class, 'reject'])->name('offer_reject');
    Route::get('/{order}/drawings', [OrderController::class, 'drawings'])->name('drawings');
    Route::post('/{order}/drawings', [OrderController::class, 'addDrawing'])->name('drawing_add');
    Route::get('/{order}/drawings/{drawing}', [OrderController::class, 'drawingFile'])->name('drawing_file');
    Route::delete('/{order}/drawings/{drawing}', [OrderController::class, 'deleteDrawing'])->name('drawing_delete');
    Route::put('/{order}/drawings-complete', [OrderController::class, 'declareDrawings'])->name('drawings_declare');
    // Przekazanie zlecenia ma wlasna akcje, tak jak kazda inna decyzja
    // w tym module — nie ma tu wspolnego PUT na cale zlecenie.
    Route::put('/{order}/owner', [OrderController::class, 'changeOwner'])->name('owner');
    Route::get('/{order}/payments', [OrderController::class, 'payments'])->name('payments');
    Route::post('/{order}/payments', [OrderController::class, 'addPayment'])->name('payment_add');
    // Korekta dopisuje wiersz, nie kasuje — stad POST.
    Route::post('/{order}/payments/{payment}/reverse', [OrderController::class, 'reversePayment'])->name('payment_reverse');
});
