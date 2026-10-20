<?php

namespace App\Policies;

use App\Models\DeviceGroup;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationAssets;
use App\Support\CurrentOrganization;

class DeviceGroupPolicy
{
    use AuthorizesOrganizationAssets;

    public function viewAny(User $user): bool
    {
        $organization = CurrentOrganization::get();

        return $organization !== null && $this->isMember($user, $organization);
    }

    public function view(User $user, DeviceGroup $deviceGroup): bool
    {
        return $this->organizationIdMatches($user, $deviceGroup->organization_id);
    }

    public function create(User $user): bool
    {
        $organization = CurrentOrganization::get();

        return $organization !== null && $this->canManage($user, $organization);
    }

    public function update(User $user, DeviceGroup $deviceGroup): bool
    {
        $organization = CurrentOrganization::get();

        return $organization !== null
            && $organization->id === $deviceGroup->organization_id
            && $this->canManage($user, $organization);
    }

    public function delete(User $user, DeviceGroup $deviceGroup): bool
    {
        return $this->update($user, $deviceGroup);
    }
}
