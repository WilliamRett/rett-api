<?php

use App\Http\Controllers\Health\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HealthController::class, 'index'])->name('health_check');
