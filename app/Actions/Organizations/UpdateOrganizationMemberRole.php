<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use App\Support\CurrentOrganization;
use App\Support\SystemAuditRecorder;
use Illuminate\Validation\ValidationException;

class UpdateOrganizationMemberRole
{
    /**
     * @throws ValidationException
     */
    public function handle(Organization $organization, User $member, OrganizationRole $role): void
    {
        if (! $organization->users()->where('users.id', $member->id)->exists()) {
            throw ValidationException::withMessages([
                'member' => __('This user is not a member of the organization.'),
            ]);
        }

        if ($role === OrganizationRole::Owner) {
            throw ValidationException::withMessages([
                'role' => __('The owner role cannot be assigned this way.'),
            ]);
        }

        $currentRole = CurrentOrganization::roleFor($member, $organization);

        if ($currentRole === OrganizationRole::Owner) {
            throw ValidationException::withMessages([
                'role' => __('The organization owner role cannot be changed this way.'),
            ]);
        }

        $organization->users()->updateExistingPivot($member->id, [
            'role' => $role->value,
        ]);

        app(SystemAuditRecorder::class)->record('organization_member.role_updated', $member, $organization->id);
    }
}
