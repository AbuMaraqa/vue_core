<?php

use App\Modules\Inventory\Http\Controllers\InventoryReportController;
use App\Modules\Inventory\Http\Controllers\ItemCategoryController;
use App\Modules\Inventory\Http\Controllers\ItemController;
use App\Modules\Inventory\Http\Controllers\StockAdjustmentController;
use App\Modules\Inventory\Http\Controllers\StockTransferController;
use App\Modules\Inventory\Http\Controllers\UnitController;
use App\Modules\Inventory\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified', 'can:Dashboard View'])
    ->prefix('{locale}/admin/inventory')
    ->name('admin.inventory.')
    ->group(function () {
        Route::get('items/search', [ItemController::class, 'search'])->name('items.search');
        Route::post('items/quick', [ItemController::class, 'quickStore'])->name('items.quick-store');
        Route::get('items/export', [ItemController::class, 'export'])->can('Item Export')->name('items.export');
        Route::get('items/import/template', [ItemController::class, 'template'])->can('Item Import')->name('items.import-template');
        Route::post('items/import', [ItemController::class, 'import'])->can('Item Import')->middleware('throttle:10,1')->name('items.import');
        Route::resource('items', ItemController::class);
        Route::get('items/{item}/pdf', [ItemController::class, 'pdf'])->name('items.pdf');

        Route::resource('categories', ItemCategoryController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('units', UnitController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('warehouses', WarehouseController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::resource('adjustments', StockAdjustmentController::class)->only(['index', 'create', 'store', 'show']);
        Route::get('adjustments/{adjustment}/pdf', [StockAdjustmentController::class, 'pdf'])->name('adjustments.pdf');
        Route::post('adjustments/{adjustment}/cancel', [StockAdjustmentController::class, 'cancel'])->name('adjustments.cancel');

        Route::resource('transfers', StockTransferController::class)->only(['index', 'create', 'store', 'show']);
        Route::get('transfers/{transfer}/pdf', [StockTransferController::class, 'pdf'])->name('transfers.pdf');
        Route::post('transfers/{transfer}/cancel', [StockTransferController::class, 'cancel'])->name('transfers.cancel');

        Route::prefix('reports')->name('reports.')->controller(InventoryReportController::class)->group(function () {
            Route::get('valuation', 'valuation')->name('valuation');
            Route::get('stock-card', 'stockCard')->name('stock-card');
        });
    });
