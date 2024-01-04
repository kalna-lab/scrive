<?php

use Illuminate\Support\Facades\Route;
use KalnaLab\Scrive\Http\Controllers\ScriveController;
use KalnaLab\Scrive\Http\Controllers\PostingController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('scrive')->middleware('web')->group(function () {
});
