<?php

use App\Modules\Contacts\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified', 'can:Dashboard View'])
    ->prefix('{locale}/admin')
    ->name('admin.')
    ->group(function () {
        Route::get('contacts/search', [ContactController::class, 'search'])->name('contacts.search');
        Route::post('contacts/quick', [ContactController::class, 'quickStore'])->name('contacts.quick-store');
        Route::get('contacts/export', [ContactController::class, 'export'])
            ->can('Contact Export')
            ->name('contacts.export');
        Route::get('contacts/import/template', [ContactController::class, 'template'])
            ->can('Contact Import')
            ->name('contacts.import-template');
        Route::post('contacts/import', [ContactController::class, 'import'])
            ->can('Contact Import')
            ->middleware('throttle:10,1')
            ->name('contacts.import');

        Route::get('contacts/{contact}/pdf', [ContactController::class, 'pdf'])->name('contacts.pdf');

        Route::resource('contacts', ContactController::class);
    });
