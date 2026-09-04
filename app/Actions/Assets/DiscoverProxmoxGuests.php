<?php

namespace App\Actions\Assets;

use App\Data\DiscoveredProxmoxGuest;
use App\Models\Hardware;
use App\Services\Proxmox\ProxmoxGuestDiscoveryService;
use RuntimeException;

class DiscoverProxmoxGuests
{
    public function __construct(
        protected ProxmoxGuestDiscoveryService $discoveryService,
    ) {}

    /**
     * @return list<DiscoveredProxmoxGuest>
     *
     * @throws RuntimeException
     */
    public function handle(Hardware $hardware): array
    {
        if (! $hardware->is_vm_host) {
            throw new RuntimeException(__('Only VM host hardware can sync with Proxmox.'));
        }

        if (! $hardware->hasProxmoxCredentials()) {
            throw new RuntimeException(__('Add Proxmox credentials before discovering guests.'));
        }

        $discovered = $this->discoveryService->discover($hardware);

        $hardware->forceFill([
            'proxmox_credentials_verified_at' => now(),
        ])->save();

        return $discovered;
    }
}
