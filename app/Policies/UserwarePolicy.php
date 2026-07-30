<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Userware;
use App\Policies\Concerns\AuthorizesOrganizationAssets;
use App\Support\CurrentOrganization;

class UserwarePolicy
{
    use AuthorizesOrganizationAssets;

    public function viewAny(User $user): bool
    {
        $organization = CurrentOrganization::get();

        return $organization !== null && $this->isMember($user, $organization);
    }

    public function view(User $user, Userware $userware): bool
    {
        return $this->organizationIdMatches($user, $userware->organization_id);
    }

    public function create(User $user): bool
    {
        $organization = CurrentOrganization::get();

        return $organization !== null && $this->canManage($user, $organization);
    }

    public function update(User $user, Userware $userware): bool
    {
        $organization = CurrentOrganization::get();

        return $organization !== null
            && $organization->id === $userware->organization_id
            && $this->canManage($user, $organization);
    }

    public function delete(User $user, Userware $userware): bool
    {
        return $this->update($user, $userware);
    }
}
