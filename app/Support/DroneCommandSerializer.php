<?php

namespace App\Support;

use App\Models\Hardware;
use App\Models\Organization;
use App\Models\RemotePushTarget;

class DroneCommandSerializer
{
    public function findHardware(Organization $organization, string $serialNumber, string $deviceName): ?Hardware
    {
        if ($serialNumber !== '') {
            $bySerial = $organization->hardwares()
                ->where('serial_number', $serialNumber)
                ->first();
            if ($bySerial !== null) {
                return $bySerial;
            }
        }

        if ($deviceName !== '') {
            return $organization->hardwares()
                ->whereRaw('LOWER(name) = ?', [strtolower($deviceName)])
                ->first();
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeTarget(RemotePushTarget $target): array
    {
        $push = $target->remotePush;
        $push->loadMissing(['packages', 'package']);

        $packages = $push->packages
            ->map(fn ($package) => $package->toDronePayload())
            ->values()
            ->all();

        if ($packages === [] && $push->package !== null) {
            $packages = [$push->package->toDronePayload()];
        }

        return [
            'id' => $target->id,
            'pushId' => $push->id,
            'type' => $push->type->value,
            'status' => $target->status->value,
            'requiresUserConsent' => $push->type->requiresUserConsent(),
            'firstNotifiedAt' => $target->first_notified_at?->toIso8601String(),
            'postponeDeadlineAt' => $target->postpone_deadline_at?->toIso8601String(),
            'scheduledFor' => $target->scheduled_for?->toIso8601String(),
            'package' => $packages[0] ?? null,
            'packages' => $packages,
        ];
    }
}
