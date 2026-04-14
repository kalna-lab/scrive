<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use KalnaLab\Scrive\Http\Controllers\ScriveController;

/*
|--------------------------------------------------------------------------
| Scrive authentication callback route
|--------------------------------------------------------------------------
|
| Registers the callback endpoint that Scrive redirects the user to after
| completing an eID transaction. The path is configurable via
| `scrive.auth.redirect-path`.
|
*/

Route::middleware(['web', 'guest'])->group(function () {
    Route::get(
        config('scrive.auth.redirect-path', '/login'),
        [ScriveController::class, 'authenticate']
    )->name('scrive.authenticate');
});
