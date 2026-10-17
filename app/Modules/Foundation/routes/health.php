<?php

use App\Modules\Foundation\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

/*
 * Kept outside the web middleware group: no session, cookies or locale, so
 * monitors can call it as often as they need.
 */
Route::get('health', HealthController::class)->name('health');
