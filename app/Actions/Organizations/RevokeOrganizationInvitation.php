<?php

namespace App\Actions\Organizations;

use App\Models\OrganizationInvitation;

class RevokeOrganizationInvitation
{
    public function handle(OrganizationInvitation $invitation): void
    {
        $invitation->delete();
    }
}
