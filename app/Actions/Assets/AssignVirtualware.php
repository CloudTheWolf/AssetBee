<?php

namespace App\Actions\Assets;

use App\Models\CloudTenant;
use App\Models\Hardware;
use App\Models\Userware;
use App\Models\Virtualware;
use Illuminate\Validation\ValidationException;

class AssignVirtualware
{
    /**
     * @throws ValidationException
     */
    public function handle(
        Virtualware $virtualware,
        ?Userware $userware = null,
        ?Hardware $host = null,
        bool $updateHost = false,
        ?CloudTenant $cloudTenant = null,
        bool $updateCloudTenant = false,
    ): Virtualware {
        if ($userware !== null && $userware->organization_id !== $virtualware->organization_id) {
            throw ValidationException::withMessages([
                'assigned_userware_id' => __('The selected identity belongs to another organization.'),
            ]);
        }

        if ($updateHost && $host !== null) {
            $this->assertValidVmHost($virtualware, $host);
        }

        if ($updateCloudTenant && $cloudTenant !== null && $cloudTenant->organization_id !== $virtualware->organization_id) {
            throw ValidationException::withMessages([
                'cloud_tenant_id' => __('The selected cloud tenant belongs to another organization.'),
            ]);
        }

        if ($updateHost && $updateCloudTenant && $host !== null && $cloudTenant !== null) {
            throw ValidationException::withMessages([
                'host_hardware_id' => __('Virtualware can be linked to a cloud tenant or a VM host, not both.'),
            ]);
        }

        $virtualware->assigned_userware_id = $userware?->id;

        if ($updateHost && $updateCloudTenant) {
            // Exclusive placement update from the UI: exactly one side may be set.
            $virtualware->host_hardware_id = $host?->id;
            $virtualware->cloud_tenant_id = $cloudTenant?->id;
        } elseif ($updateHost) {
            $virtualware->host_hardware_id = $host?->id;

            if ($host !== null) {
                $virtualware->cloud_tenant_id = null;
            }
        } elseif ($updateCloudTenant) {
            $virtualware->cloud_tenant_id = $cloudTenant?->id;

            if ($cloudTenant !== null) {
                $virtualware->host_hardware_id = null;
            }
        }

        $virtualware->save();

        return $virtualware->refresh();
    }

    /**
     * @throws ValidationException
     */
    protected function assertValidVmHost(Virtualware $virtualware, Hardware $host): void
    {
        if ($host->organization_id !== $virtualware->organization_id) {
            throw ValidationException::withMessages([
                'host_hardware_id' => __('The selected host belongs to another organization.'),
            ]);
        }

        if (! $host->is_vm_host || ! $host->category->canBeVmHost()) {
            throw ValidationException::withMessages([
                'host_hardware_id' => __('The selected hardware must be a server marked as a VM host.'),
            ]);
        }
    }
}
