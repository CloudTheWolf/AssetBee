<?php

namespace App\Actions\Assets;

use App\Data\DiscoveredCloudVirtualMachine;
use App\Models\CloudTenant;
use App\Services\Cloud\CloudVirtualMachineDiscoveryManager;
use RuntimeException;

class DiscoverCloudVirtualMachines
{
    public function __construct(
        protected CloudVirtualMachineDiscoveryManager $discoveryManager,
    ) {}

    /**
     * @return list<DiscoveredCloudVirtualMachine>
     *
     * @throws RuntimeException
     */
    public function handle(CloudTenant $cloudTenant, ?string $region = null): array
    {
        if (! $cloudTenant->provider->supportsVmImport()) {
            throw new RuntimeException(__('VM import is not supported for this cloud provider yet.'));
        }

        if (! $cloudTenant->hasCredentials()) {
            throw new RuntimeException(__('Add credentials before discovering virtual machines.'));
        }

        $discovered = $this->discoveryManager->discover($cloudTenant, $region);

        $cloudTenant->forceFill([
            'credentials_verified_at' => now(),
        ])->save();

        return $discovered;
    }
}
