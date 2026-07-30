<?php

namespace App\Policies;

use App\Models\AssetDocument;
use App\Models\Hardware;
use App\Models\Software;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationAssets;
use App\Support\CurrentOrganization;

class AssetDocumentPolicy
{
    use AuthorizesOrganizationAssets;

    public function view(User $user, AssetDocument $document): bool
    {
        return $this->organizationIdMatches($user, $document->organization_id);
    }

    public function create(User $user, Hardware|Software $documentable): bool
    {
        $organization = CurrentOrganization::get();

        return $organization !== null
            && $organization->id === $documentable->organization_id
            && $this->canManage($user, $organization);
    }

    public function delete(User $user, AssetDocument $document): bool
    {
        $organization = CurrentOrganization::get();

        return $organization !== null
            && $organization->id === $document->organization_id
            && $this->canManage($user, $organization);
    }
}
