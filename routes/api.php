<?php

declare(strict_types = 1);

use App\Http\Controllers\Api\V1\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware('api')
    ->group(function (): void {
        Route::prefix('auth')->group(function (): void {
            Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
            Route::post('/login', [AuthController::class, 'login']);

            Route::middleware('auth:api')->group(function (): void {
                Route::get('/logged', [AuthController::class, 'logged']);
                Route::post('/logout', [AuthController::class, 'logout']);
            });
        });
    });
