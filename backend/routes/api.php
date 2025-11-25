<?php

use App\Http\Controllers\ListController;
use Illuminate\Support\Facades\Route;

Route::middleware('api')->group(function () {
    Route::post('/upload', [ListController::class, 'process'])->name('upload.process');
});

