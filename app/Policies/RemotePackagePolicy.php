<?php

namespace App\Policies;

use App\Models\RemotePackage;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationAssets;
use App\Support\CurrentOrganization;

class RemotePackagePolicy
{
    use AuthorizesOrganizationAssets;

    public function viewAny(User $user): bool
    {
        $organization = CurrentOrganization::get();

        return $organization !== null && $this->canManage($user, $organization);
    }

    public function view(User $user, RemotePackage $remotePackage): bool
    {
        $organization = CurrentOrganization::get();

        return $organization !== null
            && $organization->id === $remotePackage->organization_id
            && $this->canManage($user, $organization);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, RemotePackage $remotePackage): bool
    {
        return $this->view($user, $remotePackage);
    }

    public function delete(User $user, RemotePackage $remotePackage): bool
    {
        return $this->view($user, $remotePackage);
    }
}
