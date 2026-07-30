<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Virtualware;
use App\Policies\Concerns\AuthorizesOrganizationAssets;
use App\Support\CurrentOrganization;

class VirtualwarePolicy
{
    use AuthorizesOrganizationAssets;

    public function viewAny(User $user): bool
    {
        $organization = CurrentOrganization::get();

        return $organization !== null && $this->isMember($user, $organization);
    }

    public function view(User $user, Virtualware $virtualware): bool
    {
        return $this->organizationIdMatches($user, $virtualware->organization_id);
    }

    public function create(User $user): bool
    {
        $organization = CurrentOrganization::get();

        return $organization !== null && $this->canManage($user, $organization);
    }

    public function update(User $user, Virtualware $virtualware): bool
    {
        $organization = CurrentOrganization::get();

        return $organization !== null
            && $organization->id === $virtualware->organization_id
            && $this->canManage($user, $organization);
    }

    public function delete(User $user, Virtualware $virtualware): bool
    {
        return $this->update($user, $virtualware);
    }

    public function assign(User $user, Virtualware $virtualware): bool
    {
        return $this->update($user, $virtualware);
    }
}
