<?php

namespace App\Policies;

use App\Models\CloudTenant;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationAssets;
use App\Support\CurrentOrganization;

class CloudTenantPolicy
{
    use AuthorizesOrganizationAssets;

    public function viewAny(User $user): bool
    {
        $organization = CurrentOrganization::get();

        return $organization !== null && $this->isMember($user, $organization);
    }

    public function view(User $user, CloudTenant $cloudTenant): bool
    {
        return $this->organizationIdMatches($user, $cloudTenant->organization_id);
    }

    public function create(User $user): bool
    {
        $organization = CurrentOrganization::get();

        return $organization !== null && $this->canManage($user, $organization);
    }

    public function update(User $user, CloudTenant $cloudTenant): bool
    {
        $organization = CurrentOrganization::get();

        return $organization !== null
            && $organization->id === $cloudTenant->organization_id
            && $this->canManage($user, $organization);
    }

    public function delete(User $user, CloudTenant $cloudTenant): bool
    {
        return $this->update($user, $cloudTenant);
    }
}
