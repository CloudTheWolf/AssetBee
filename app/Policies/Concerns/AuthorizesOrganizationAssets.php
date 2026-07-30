<?php

namespace App\Policies\Concerns;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use App\Support\CurrentOrganization;

trait AuthorizesOrganizationAssets
{
    protected function roleInOrganization(User $user, Organization $organization): ?OrganizationRole
    {
        return CurrentOrganization::roleFor($user, $organization);
    }

    protected function isMember(User $user, Organization $organization): bool
    {
        return $this->roleInOrganization($user, $organization) !== null;
    }

    protected function canManage(User $user, Organization $organization): bool
    {
        return $this->roleInOrganization($user, $organization)?->canManageAssets() ?? false;
    }

    protected function organizationIdMatches(User $user, int $organizationId): bool
    {
        $organization = CurrentOrganization::get();

        if ($organization === null || $organization->id !== $organizationId) {
            return false;
        }

        return $this->isMember($user, $organization);
    }
}
