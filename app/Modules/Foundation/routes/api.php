<?php

use App\Modules\Foundation\Http\Controllers\Api\V1\AuthTokenController;
use App\Modules\Foundation\Http\Controllers\Api\V1\CurrentUserController;
use App\Modules\Foundation\Http\Controllers\Api\V1\NotificationController;
use App\Modules\Foundation\Http\Middleware\SetApiLocale;
use Illuminate\Support\Facades\Route;

/*
 * Version 1 of the JSON API. A breaking change gets a new version prefix
 * instead of changing these endpoints. Documented at /api/documentation.
 */
Route::middleware(['api', SetApiLocale::class, 'throttle:api'])
    ->prefix('api/v1')
    ->name('api.v1.')
    ->group(function () {
        Route::post('auth/token', [AuthTokenController::class, 'store'])
            ->middleware('throttle:api-token')
            ->name('auth.token.store');

        Route::middleware('auth:sanctum')->group(function () {
            Route::delete('auth/token', [AuthTokenController::class, 'destroy'])->name('auth.token.destroy');
            Route::get('me', CurrentUserController::class)->name('me');

            Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
            Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
            Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
        });
    });
