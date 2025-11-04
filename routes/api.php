<?php

declare(strict_types = 1);

use App\Http\Controllers\InstanceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware('api')
    ->group(function (): void {
        Route::apiResource('instancias', InstanceController::class)
            ->only(['index', 'store', 'destroy', 'show']);
    });
