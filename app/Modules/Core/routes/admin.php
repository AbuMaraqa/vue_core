<?php

use App\Modules\Core\Enums\Permissions\DashboardPermission;
use App\Modules\Core\Http\Controllers\ActivityLogController;
use App\Modules\Core\Http\Controllers\DashboardController;
use App\Modules\Core\Http\Controllers\MediaController;
use App\Modules\Core\Http\Controllers\RoleController;
use App\Modules\Core\Http\Controllers\SettingController;
use App\Modules\Core\Http\Controllers\TemporaryUploadController;
use App\Modules\Core\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified', 'can:'.DashboardPermission::Access->value])
    ->prefix('{locale}/admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::resource('users', UserController::class)->except('show');
        Route::resource('roles', RoleController::class)->except('show');

        Route::get('activity-log', ActivityLogController::class)->name('activity-log.index');

        Route::get('media', [MediaController::class, 'index'])->name('media.index');
        Route::get('media/browse', [MediaController::class, 'browse'])->name('media.browse');
        Route::post('media', [MediaController::class, 'store'])->name('media.store');
        Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');

        Route::middleware('throttle:60,1')->group(function () {
            Route::post('uploads', [TemporaryUploadController::class, 'store'])->name('uploads.store');
            Route::delete('uploads/{temporaryUpload}', [TemporaryUploadController::class, 'destroy'])->name('uploads.destroy');
        });

        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    });
