<?php

use App\Modules\Production\Http\Controllers\BillOfMaterialsController;
use App\Modules\Production\Http\Controllers\ProductionOrderController;
use App\Modules\Production\Http\Controllers\ProductionReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified', 'can:Dashboard View'])
    ->prefix('{locale}/admin/production')
    ->name('admin.production.')
    ->group(function () {
        Route::resource('boms', BillOfMaterialsController::class)->except('show');

        Route::resource('orders', ProductionOrderController::class);
        Route::get('orders/{order}/pdf', [ProductionOrderController::class, 'pdf'])->name('orders.pdf');
        Route::post('orders/{order}/start', [ProductionOrderController::class, 'start'])->name('orders.start');
        Route::get('orders/{order}/complete', [ProductionOrderController::class, 'completion'])->name('orders.completion');
        Route::post('orders/{order}/complete', [ProductionOrderController::class, 'complete'])->name('orders.complete');
        Route::post('orders/{order}/reverse', [ProductionOrderController::class, 'reverse'])->name('orders.reverse');

        Route::get('reports/summary', [ProductionReportController::class, 'summary'])->name('reports.summary');
    });
