<?php

use App\Modules\Assets\Http\Controllers\AssetCategoryController;
use App\Modules\Assets\Http\Controllers\AssetController;
use App\Modules\Assets\Http\Controllers\DepreciationRunController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified', 'can:Dashboard View'])
    ->prefix('{locale}/admin/assets')
    ->name('admin.assets.')
    ->group(function () {
        Route::get('reports/register', [AssetController::class, 'register'])->name('reports.register');

        Route::resource('categories', AssetCategoryController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::get('depreciation', [DepreciationRunController::class, 'index'])->name('depreciation.index');
        Route::post('depreciation', [DepreciationRunController::class, 'store'])->name('depreciation.store');
        Route::get('depreciation/{run}', [DepreciationRunController::class, 'show'])->name('depreciation.show');
        Route::get('depreciation/{run}/pdf', [DepreciationRunController::class, 'pdf'])->name('depreciation.pdf');
        Route::post('depreciation/{run}/cancel', [DepreciationRunController::class, 'cancel'])->name('depreciation.cancel');

        Route::resource('assets', AssetController::class)->only(['index', 'create', 'store', 'show', 'update']);
        Route::get('assets/{asset}/pdf', [AssetController::class, 'pdf'])->name('assets.pdf');
        Route::post('assets/{asset}/dispose', [AssetController::class, 'dispose'])->name('assets.dispose');
        Route::post('assets/{asset}/cancel', [AssetController::class, 'cancel'])->name('assets.cancel');
    });
