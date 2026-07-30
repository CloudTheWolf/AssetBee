<?php

namespace App\Policies;

use App\Models\SoftwareAssignment;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationAssets;
use App\Support\CurrentOrganization;

class SoftwareAssignmentPolicy
{
    use AuthorizesOrganizationAssets;

    public function delete(User $user, SoftwareAssignment $softwareAssignment): bool
    {
        $softwareAssignment->loadMissing('software');

        $organization = CurrentOrganization::get();

        return $organization !== null
            && $softwareAssignment->software !== null
            && $organization->id === $softwareAssignment->software->organization_id
            && $this->canManage($user, $organization);
    }
}
