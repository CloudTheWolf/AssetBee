<?php

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationAssets;
use App\Support\CloudMode;
use App\Support\CurrentOrganization;
use App\Support\Registration;

class OrganizationPolicy
{
    use AuthorizesOrganizationAssets;

    public function viewAny(User $user): bool
    {
        return $user->hasSystemAccess() || $user->organizations()->exists();
    }

    public function view(User $user, Organization $organization): bool
    {
        if ($user->isSystem()) {
            return CurrentOrganization::isManagedBySystem($user, $organization);
        }

        return $this->isMember($user, $organization);
    }

    public function create(User $user): bool
    {
        if ($user->isSystem()) {
            return false;
        }

        if (! Registration::selfHosted()) {
            return true;
        }

        return $user->organizations()->wherePivot('role', OrganizationRole::Owner->value)->exists()
            || ! Organization::query()->exists();
    }

    public function update(User $user, Organization $organization): bool
    {
        if ($user->hasSystemAccess()) {
            return CurrentOrganization::isManagedBySystem($user, $organization);
        }

        return $this->roleInOrganization($user, $organization)?->canManageOrganization() ?? false;
    }

    public function manage(User $user, Organization $organization): bool
    {
        return $this->update($user, $organization);
    }

    public function manageApiKeys(User $user, Organization $organization): bool
    {
        if ($user->isSystem()) {
            return false;
        }

        return $this->roleInOrganization($user, $organization)?->canManageOrganization() ?? false;
    }

    public function manageSubscription(User $user, Organization $organization): bool
    {
        return CurrentOrganization::isManagedBySystem($user, $organization);
    }

    public function manageBilling(User $user, Organization $organization): bool
    {
        if ($user->isSystem()) {
            return false;
        }

        return $user->isCustomer()
            && CloudMode::enabled()
            && $this->roleInOrganization($user, $organization) === OrganizationRole::Owner;
    }

    public function invite(User $user, Organization $organization): bool
    {
        if ($user->hasSystemAccess()) {
            return CurrentOrganization::isManagedBySystem($user, $organization);
        }

        return $this->roleInOrganization($user, $organization)?->canInviteMembers() ?? false;
    }

    public function delete(User $user, Organization $organization): bool
    {
        if ($user->isSystem()) {
            return false;
        }

        return $this->roleInOrganization($user, $organization) === OrganizationRole::Owner;
    }
}
