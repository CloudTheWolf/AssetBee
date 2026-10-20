<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RemotePushStatus;
use App\Enums\RemotePushTargetStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\RemotePushTarget;
use App\Support\DroneCommandSerializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerifyDroneCommandController extends Controller
{
    public function __invoke(
        Request $request,
        int $targetId,
        DroneCommandSerializer $serializer,
    ): JsonResponse {
        /** @var Organization $organization */
        $organization = $request->attributes->get('organization');

        $serialNumber = trim((string) $request->input('serialNumber', $request->query('serialNumber', '')));
        $deviceName = trim((string) $request->input('deviceName', $request->query('deviceName', '')));

        if ($serialNumber === '' && $deviceName === '') {
            return response()->json([
                'authorized' => false,
                'reason' => 'serialNumber or deviceName is required.',
                'command' => null,
            ], 422);
        }

        $hardware = $serializer->findHardware($organization, $serialNumber, $deviceName);
        if ($hardware === null) {
            return response()->json([
                'authorized' => false,
                'reason' => 'Device is not registered to this organization.',
                'command' => null,
            ], 403);
        }

        $target = RemotePushTarget::query()
            ->with(['remotePush.package'])
            ->whereKey($targetId)
            ->where('hardware_id', $hardware->id)
            ->whereHas('remotePush', function ($query) use ($organization): void {
                $query->where('organization_id', $organization->id);
            })
            ->first();

        if ($target === null) {
            return response()->json([
                'authorized' => false,
                'reason' => 'Command target was not found for this device.',
                'command' => null,
            ], 404);
        }

        $push = $target->remotePush;
        if ($push->status !== RemotePushStatus::Open) {
            return response()->json([
                'authorized' => false,
                'reason' => 'Push is no longer open.',
                'command' => null,
            ], 403);
        }

        if ($target->status->isTerminal()) {
            return response()->json([
                'authorized' => false,
                'reason' => 'Command target is already finished.',
                'command' => null,
            ], 403);
        }

        if (! in_array($target->status, RemotePushTargetStatus::incompleteCases(), true)) {
            return response()->json([
                'authorized' => false,
                'reason' => 'Command target is not executable.',
                'command' => null,
            ], 403);
        }

        if ($push->type->requiresPackage() && $push->package === null) {
            return response()->json([
                'authorized' => false,
                'reason' => 'Install package is missing from the catalog.',
                'command' => null,
            ], 403);
        }

        return response()->json([
            'authorized' => true,
            'reason' => null,
            'command' => $serializer->serializeTarget($target),
        ]);
    }
}
