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

class ListDroneCommandsController extends Controller
{
    public function __invoke(Request $request, DroneCommandSerializer $serializer): JsonResponse
    {
        /** @var Organization $organization */
        $organization = $request->attributes->get('organization');

        $serialNumber = trim((string) $request->query('serialNumber', ''));
        $deviceName = trim((string) $request->query('deviceName', ''));

        if ($serialNumber === '' && $deviceName === '') {
            return response()->json([
                'message' => 'serialNumber or deviceName is required.',
            ], 422);
        }

        $hardware = $serializer->findHardware($organization, $serialNumber, $deviceName);

        if ($hardware === null) {
            return response()->json([
                'data' => [],
                'hardwareId' => null,
            ]);
        }

        $targets = RemotePushTarget::query()
            ->with(['remotePush.packages', 'remotePush.package'])
            ->where('hardware_id', $hardware->id)
            ->whereHas('remotePush', function ($query) use ($organization): void {
                $query->where('organization_id', $organization->id)
                    ->where('status', RemotePushStatus::Open);
            })
            ->whereIn('status', array_map(
                static fn (RemotePushTargetStatus $status): string => $status->value,
                RemotePushTargetStatus::incompleteCases(),
            ))
            ->orderBy('id')
            ->get()
            ->map(fn (RemotePushTarget $target): array => $serializer->serializeTarget($target))
            ->values();

        return response()->json([
            'data' => $targets,
            'hardwareId' => $hardware->id,
        ]);
    }
}
