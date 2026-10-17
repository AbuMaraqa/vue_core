<?php

use App\Modules\Foundation\Http\Controllers\NotificationController;
use App\Modules\Foundation\Http\Controllers\SessionController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

/*
 * Pages that belong to the signed-in user rather than to the admin panel,
 * next to the profile and security pages in routes/settings.php.
 */
Route::middleware(['web', 'auth', 'verified'])
    ->prefix('{locale}')
    ->group(function () {
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('notifications/latest', [NotificationController::class, 'latest'])->name('notifications.latest');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
        Route::post('notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');
        Route::delete('notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

        Route::middleware(RequirePassword::class)->group(function () {
            Route::get('settings/sessions', [SessionController::class, 'index'])->name('sessions.index');
            Route::delete('settings/sessions/others', [SessionController::class, 'destroyOthers'])->name('sessions.destroy-others');
            Route::delete('settings/sessions/{sessionKey}', [SessionController::class, 'destroy'])->name('sessions.destroy');
        });
    });
