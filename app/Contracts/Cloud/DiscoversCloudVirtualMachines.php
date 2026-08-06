<?php

namespace App\Contracts\Cloud;

use App\Data\DiscoveredCloudVirtualMachine;
use App\Models\CloudTenant;
use RuntimeException;

interface DiscoversCloudVirtualMachines
{
    public function supports(CloudTenant $tenant): bool;

    /**
     * @return list<DiscoveredCloudVirtualMachine>
     *
     * @throws RuntimeException
     */
    public function discover(CloudTenant $tenant, ?string $region = null): array;
}
