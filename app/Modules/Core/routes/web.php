<?php

use App\Modules\Core\Http\Controllers\LocaleController;
use App\Modules\Core\Http\Controllers\LocalizedRedirectController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::get('/', LocalizedRedirectController::class)->name('root');
    Route::put('locale', LocaleController::class)->name('locale.update');

    // Old or hand-typed links without a language, e.g. /admin, are sent to /{locale}/admin.
    Route::fallback(LocalizedRedirectController::class);
});
