<?php

use App\Modules\Foundation\Http\Controllers\BackupController;
use App\Modules\Foundation\Http\Controllers\LoginHistoryController;
use App\Modules\Foundation\Http\Controllers\UserTransferController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified', 'can:Dashboard View'])
    ->prefix('{locale}/admin')
    ->name('admin.')
    ->group(function () {
        Route::get('login-history', [LoginHistoryController::class, 'index'])->name('login-history.index');
        Route::get('login-history/export', [LoginHistoryController::class, 'export'])
            ->can('Login History Export')
            ->name('login-history.export');

        Route::get('backups', [BackupController::class, 'index'])
            ->can('Backup View List')
            ->name('backups.index');
        Route::post('backups', [BackupController::class, 'store'])
            ->can('Backup Create')
            ->middleware('throttle:5,1')
            ->name('backups.store');
        Route::get('backups/{backup}/download', [BackupController::class, 'download'])
            ->can('Backup Download')
            ->name('backups.download');
        Route::delete('backups/{backup}', [BackupController::class, 'destroy'])
            ->can('Backup Delete')
            ->name('backups.destroy');

        // Export and import for Core's users list.
        Route::get('users/export', [UserTransferController::class, 'export'])
            ->can('User Export')
            ->name('users.export');
        Route::get('users/import/template', [UserTransferController::class, 'template'])
            ->can('User Import')
            ->name('users.import-template');
        Route::post('users/import', [UserTransferController::class, 'import'])
            ->can('User Import')
            ->middleware('throttle:10,1')
            ->name('users.import');
    });
