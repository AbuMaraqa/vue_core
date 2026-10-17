<?php

use Illuminate\Support\Facades\Route;

Route::prefix('{locale}')->group(function () {
    Route::inertia('/', 'Welcome')->name('home');

    require __DIR__.'/settings.php';
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
