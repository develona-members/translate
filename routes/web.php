<?php

use Develona\Translate\Controllers\TextController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {

    Route::get('texts', [TextController::class, 'index'])->name('translate.texts');
    Route::match(['get', 'post'], 'text/{id}/{lang}', [TextController::class, 'single'])->name('translate.text');

});
