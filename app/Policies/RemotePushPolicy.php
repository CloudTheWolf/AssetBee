<?php

namespace App\Policies;

use App\Models\RemotePush;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationAssets;
use App\Support\CurrentOrganization;

class RemotePushPolicy
{
    use AuthorizesOrganizationAssets;

    public function viewAny(User $user): bool
    {
        $organization = CurrentOrganization::get();

        return $organization !== null && $this->canManage($user, $organization);
    }

    public function view(User $user, RemotePush $remotePush): bool
    {
        $organization = CurrentOrganization::get();

        return $organization !== null
            && $organization->id === $remotePush->organization_id
            && $this->canManage($user, $organization);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, RemotePush $remotePush): bool
    {
        return $this->view($user, $remotePush);
    }

    public function delete(User $user, RemotePush $remotePush): bool
    {
        return $this->view($user, $remotePush);
    }
}
