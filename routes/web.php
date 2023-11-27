<?php

use Develona\Translate\Controllers\TextController;
use Illuminate\Support\Facades\Route;


Route::match(['get', 'post'], 'text/{id}/{lang}', [TextController::class, 'index'])->name('translate.text');
