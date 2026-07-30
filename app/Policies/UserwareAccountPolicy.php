<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Userware;
use App\Models\UserwareAccount;
use App\Policies\Concerns\AuthorizesOrganizationAssets;
use App\Support\CurrentOrganization;

class UserwareAccountPolicy
{
    use AuthorizesOrganizationAssets;

    public function create(User $user, Userware $userware): bool
    {
        $organization = CurrentOrganization::get();

        return $organization !== null
            && $organization->id === $userware->organization_id
            && $this->canManage($user, $organization);
    }

    public function update(User $user, UserwareAccount $account): bool
    {
        $organization = CurrentOrganization::get();

        return $organization !== null
            && $organization->id === $account->organization_id
            && $this->canManage($user, $organization);
    }

    public function delete(User $user, UserwareAccount $account): bool
    {
        return $this->update($user, $account);
    }
}
