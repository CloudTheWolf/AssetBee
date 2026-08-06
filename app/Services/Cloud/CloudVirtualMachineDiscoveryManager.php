<?php

namespace App\Services\Cloud;

use App\Contracts\Cloud\DiscoversCloudVirtualMachines;
use App\Data\DiscoveredCloudVirtualMachine;
use App\Models\CloudTenant;
use Illuminate\Support\Collection;
use RuntimeException;

class CloudVirtualMachineDiscoveryManager
{
    /**
     * @param  iterable<DiscoversCloudVirtualMachines>  $discoverers
     */
    public function __construct(
        protected iterable $discoverers,
    ) {}

    public function discovererFor(CloudTenant $tenant): DiscoversCloudVirtualMachines
    {
        foreach ($this->discoverers as $discoverer) {
            if ($discoverer->supports($tenant)) {
                return $discoverer;
            }
        }

        throw new RuntimeException(__('No VM discovery service is available for this cloud tenant.'));
    }

    public function supports(CloudTenant $tenant): bool
    {
        foreach ($this->discoverers as $discoverer) {
            if ($discoverer->supports($tenant)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<DiscoveredCloudVirtualMachine>
     */
    public function discover(CloudTenant $tenant, ?string $region = null): array
    {
        return $this->discovererFor($tenant)->discover($tenant, $region);
    }

    /**
     * @return Collection<int, DiscoversCloudVirtualMachines>
     */
    public function discoverers(): Collection
    {
        return collect($this->discoverers);
    }
}
