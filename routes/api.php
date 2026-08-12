<?php

use App\Http\Controllers\Api\V1\Soc2AuditController;
use App\Http\Controllers\Api\V1\UpsertInventoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['organization.api-key', 'throttle:60,1'])
    ->group(function (): void {
        Route::post('inventory', UpsertInventoryController::class);
        Route::put('inventory', UpsertInventoryController::class);

        Route::get('audit/soc2', [Soc2AuditController::class, 'json']);
        Route::get('audit/soc2.pdf', [Soc2AuditController::class, 'pdf']);
    });
