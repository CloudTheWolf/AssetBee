<?php

use App\Http\Controllers\Api\V1\UpsertInventoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['organization.api-key', 'throttle:60,1'])
    ->group(function (): void {
        Route::post('inventory', UpsertInventoryController::class);
        Route::put('inventory', UpsertInventoryController::class);
    });
