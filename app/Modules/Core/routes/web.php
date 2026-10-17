<?php

use App\Modules\Core\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::put('locale', LocaleController::class)->name('locale.update');
});
