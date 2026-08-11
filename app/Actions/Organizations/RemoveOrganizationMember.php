<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use App\Support\CurrentOrganization;
use App\Support\SystemAuditRecorder;
use Illuminate\Validation\ValidationException;

class RemoveOrganizationMember
{
    /**
     * @throws ValidationException
     */
    public function handle(Organization $organization, User $member): void
    {
        $role = CurrentOrganization::roleFor($member, $organization);

        if ($role === null) {
            throw ValidationException::withMessages([
                'member' => __('This user is not a member of the organization.'),
            ]);
        }

        if ($role === OrganizationRole::Owner) {
            throw ValidationException::withMessages([
                'member' => __('The organization owner cannot be removed.'),
            ]);
        }

        $organization->users()->detach($member->id);
        app(SystemAuditRecorder::class)->record('organization_member.removed', $member, $organization->id);
    }
}
