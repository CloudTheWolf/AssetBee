<?php

use App\Http\Controllers\Api\V1\DroneSettingsController;
use App\Http\Controllers\Api\V1\ListDroneCommandsController;
use App\Http\Controllers\Api\V1\Soc2AuditController;
use App\Http\Controllers\Api\V1\UpdateDroneCommandStatusController;
use App\Http\Controllers\Api\V1\UpsertInventoryController;
use App\Http\Controllers\Api\V1\VerifyDroneCommandController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['organization.api-key', 'throttle:60,1'])
    ->group(function (): void {
        Route::post('inventory', UpsertInventoryController::class);
        Route::put('inventory', UpsertInventoryController::class);

        Route::get('drone/settings', DroneSettingsController::class);
        Route::get('drone/commands', ListDroneCommandsController::class);
        Route::post('drone/commands/{targetId}/verify', VerifyDroneCommandController::class)
            ->whereNumber('targetId');
        Route::post('drone/commands/{targetId}/status', UpdateDroneCommandStatusController::class)
            ->whereNumber('targetId');

        Route::get('audit/soc2', [Soc2AuditController::class, 'json']);
        Route::get('audit/soc2.pdf', [Soc2AuditController::class, 'pdf']);
    });
