<?php

namespace App\Policies;

use App\Models\Software;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationAssets;
use App\Support\CurrentOrganization;

class SoftwarePolicy
{
    use AuthorizesOrganizationAssets;

    public function viewAny(User $user): bool
    {
        $organization = CurrentOrganization::get();

        return $organization !== null && $this->isMember($user, $organization);
    }

    public function view(User $user, Software $software): bool
    {
        return $this->organizationIdMatches($user, $software->organization_id);
    }

    public function create(User $user): bool
    {
        $organization = CurrentOrganization::get();

        return $organization !== null && $this->canManage($user, $organization);
    }

    public function update(User $user, Software $software): bool
    {
        $organization = CurrentOrganization::get();

        return $organization !== null
            && $organization->id === $software->organization_id
            && $this->canManage($user, $organization);
    }

    public function delete(User $user, Software $software): bool
    {
        return $this->update($user, $software);
    }

    public function assign(User $user, Software $software): bool
    {
        return $this->update($user, $software);
    }
}
