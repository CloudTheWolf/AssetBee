<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DroneSettingsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Organization $organization */
        $organization = $request->attributes->get('organization');

        return response()->json([
            'enableDriveEncryption' => (bool) $organization->enable_drive_encryption,
        ]);
    }
}
