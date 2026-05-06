<?php

use App\Http\Controllers\Api\ImportController;
use Illuminate\Support\Facades\Route;

Route::get('imports', [ImportController::class, 'index'])->name('api.imports.index');
Route::post('imports', [ImportController::class, 'store'])->name('api.imports.store');
Route::get('imports/{import}', [ImportController::class, 'show'])->name('api.imports.show');
