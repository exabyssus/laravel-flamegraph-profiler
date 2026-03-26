<?php

use Exabyssus\LaravelProfiler\Http\Controllers\ProfilerController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ProfilerController::class, 'index'])->name('index');
Route::get('/requests/{id}', [ProfilerController::class, 'show'])->name('show');
Route::get('/requests/{id}/speedscope', [ProfilerController::class, 'speedscope'])->name('speedscope');