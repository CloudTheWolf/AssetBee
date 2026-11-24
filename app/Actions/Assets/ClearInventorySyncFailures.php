<?php

namespace App\Actions\Assets;

use App\Models\Hardware;
use App\Models\InventorySyncFailure;
use App\Models\Organization;
use App\Models\Virtualware;

class ClearInventorySyncFailures
{
    public function handle(
        Organization $organization,
        Hardware|Virtualware $asset,
        string $serialNumber,
        string $deviceName,
    ): void {
        $identityKeys = array_values(array_filter([
            InventorySyncFailure::identityKey($serialNumber, $deviceName),
            InventorySyncFailure::identityKey((string) ($asset->serial_number ?? ''), (string) $asset->name),
        ]));

        $query = InventorySyncFailure::query()->where('organization_id', $organization->id);

        $query->where(function ($builder) use ($asset, $identityKeys): void {
            if ($asset instanceof Hardware) {
                $builder->orWhere('hardware_id', $asset->id);
            }

            if ($asset instanceof Virtualware) {
                $builder->orWhere('virtualware_id', $asset->id);
            }

            if ($identityKeys !== []) {
                $builder->orWhereIn('identity_key', $identityKeys);
            }
        })->delete();
    }
}
