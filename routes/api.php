<?php
// routes/api.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\Collaborator\CollaboratorController;


Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:api')->group(function () {
        Route::get('me',       [AuthController::class, 'me']);
        Route::post('logout',  [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
    });
});

Route::prefix('users')->middleware('auth:api')->group(function () {
    Route::post('/', [UserController::class, 'store']);
});

Route::prefix('collaborators')->middleware('auth:api')->group(function () {
    Route::get('/',      [CollaboratorController::class, 'list']);
    Route::get('{id}',   [CollaboratorController::class, 'show']);
    Route::post('/',     [CollaboratorController::class, 'store'])->name('collaborators.store');
    Route::patch('{id}', [CollaboratorController::class, 'patch'])->name('collaborators.update');
    Route::put('{id}',   [CollaboratorController::class, 'update']);
    Route::delete('{id}', [CollaboratorController::class, 'destroy'])->name('collaborators.destroy');
    Route::post('imports', [CollaboratorController::class, 'upload'])->name('collaborators.imports.store');
});


