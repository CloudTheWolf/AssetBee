<?php

namespace App\Actions\Assets;

use App\Data\DiscoveredProxmoxGuest;
use App\Enums\HardwareCategory;
use App\Models\Hardware;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ResolveProxmoxHostHardware
{
    /**
     * @throws ValidationException
     */
    public function handle(Hardware $connectionHost, DiscoveredProxmoxGuest $guest): Hardware
    {
        $node = strtolower(trim($guest->node));

        /** @var Collection<int, Hardware> $hosts */
        $hosts = Hardware::query()
            ->where('organization_id', $connectionHost->organization_id)
            ->vmHosts()
            ->get();

        $byCredentialNode = $hosts->filter(function (Hardware $host) use ($node): bool {
            $configured = $host->proxmoxNodeName();

            return $configured !== null && strtolower($configured) === $node;
        });

        if ($byCredentialNode->isNotEmpty()) {
            $named = $byCredentialNode->first(
                fn (Hardware $host): bool => strtolower(trim($host->name)) === $node,
            );

            return $this->assertVmHost($named instanceof Hardware ? $named : $byCredentialNode->first());
        }

        $byName = $hosts->first(
            fn (Hardware $host): bool => strtolower(trim($host->name)) === $node,
        );

        if ($byName instanceof Hardware) {
            return $this->assertVmHost($byName);
        }

        return $this->assertVmHost($connectionHost);
    }

    /**
     * @throws ValidationException
     */
    protected function assertVmHost(Hardware $host): Hardware
    {
        if (! $host->is_vm_host || $host->category !== HardwareCategory::Server) {
            throw ValidationException::withMessages([
                'host_hardware_id' => __('The resolved Proxmox host is not a valid VM host server.'),
            ]);
        }

        return $host;
    }
}
