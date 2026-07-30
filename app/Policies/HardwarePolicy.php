<?php

namespace App\Policies;

use App\Models\Hardware;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationAssets;
use App\Support\CurrentOrganization;

class HardwarePolicy
{
    use AuthorizesOrganizationAssets;

    public function viewAny(User $user): bool
    {
        $organization = CurrentOrganization::get();

        return $organization !== null && $this->isMember($user, $organization);
    }

    public function view(User $user, Hardware $hardware): bool
    {
        return $this->organizationIdMatches($user, $hardware->organization_id);
    }

    public function create(User $user): bool
    {
        $organization = CurrentOrganization::get();

        return $organization !== null && $this->canManage($user, $organization);
    }

    public function update(User $user, Hardware $hardware): bool
    {
        $organization = CurrentOrganization::get();

        return $organization !== null
            && $organization->id === $hardware->organization_id
            && $this->canManage($user, $organization);
    }

    public function delete(User $user, Hardware $hardware): bool
    {
        return $this->update($user, $hardware);
    }

    public function assign(User $user, Hardware $hardware): bool
    {
        return $this->update($user, $hardware);
    }
}
