<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\InstanceController;
use App\Http\Controllers\LicenseController;
use App\Http\Controllers\UserController;
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
        Route::middleware('auth:api')->group(function (): void {
            Route::apiResource('license', LicenseController::class);
            Route::apiResource('users', UserController::class)
                ->only(['index', 'store', 'update', 'destroy']);

            Route::post('license/delete-batch', [LicenseController::class, 'destroyBatch']);
            Route::post('license/renew-batch', [LicenseController::class, 'renewBatch']);
        });
        Route::apiResource('instancias', InstanceController::class);
        Route::post('instancias/{instancia}', [InstanceController::class, 'clone']);
        Route::put('license/{license}/metrics', [LicenseController::class, 'metrics']);
    });
