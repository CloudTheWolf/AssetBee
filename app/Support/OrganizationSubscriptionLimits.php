<?php

namespace App\Support;

use App\Enums\OrganizationLimit;
use App\Enums\UserAccountType;
use App\Models\AssetDocument;
use App\Models\CloudTenant;
use App\Models\Hardware;
use App\Models\Organization;
use App\Models\OrganizationApiKey;
use App\Models\Software;
use App\Models\Userware;
use App\Models\UserwareAccount;
use App\Models\Virtualware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class OrganizationSubscriptionLimits
{
    public function assertCanCreate(Model $model): void
    {
        $limit = match (true) {
            $model instanceof Userware => OrganizationLimit::Userware,
            $model instanceof Hardware => OrganizationLimit::Hardware,
            $model instanceof Virtualware => OrganizationLimit::Virtualware,
            $model instanceof Software => OrganizationLimit::Software,
            $model instanceof CloudTenant => OrganizationLimit::CloudTenants,
            $model instanceof AssetDocument => OrganizationLimit::AssetDocuments,
            $model instanceof UserwareAccount => OrganizationLimit::UserwareAccounts,
            $model instanceof OrganizationApiKey => OrganizationLimit::ApiKeys,
            default => null,
        };

        $organizationId = $model->getAttribute('organization_id');

        if ($limit === null || ! is_numeric($organizationId)) {
            return;
        }

        $organization = Organization::query()->find((int) $organizationId);

        if ($organization === null) {
            return;
        }

        $this->assertWithinLimit($organization, $limit, $this->usage($organization, $limit));
    }

    public function assertCanAddMember(Organization $organization): void
    {
        $this->assertWithinLimit(
            $organization,
            OrganizationLimit::Members,
            $this->usage($organization, OrganizationLimit::Members),
        );
    }

    public function usage(Organization $organization, OrganizationLimit $limit): int
    {
        return match ($limit) {
            OrganizationLimit::Members => $organization->users()
                ->where('users.account_type', UserAccountType::Customer)
                ->count() + $organization->invitations()->pending()->count(),
            OrganizationLimit::Userware => $organization->userwares()->count(),
            OrganizationLimit::Hardware => $organization->hardwares()->count(),
            OrganizationLimit::Virtualware => $organization->virtualwares()->count(),
            OrganizationLimit::Software => $organization->softwares()->count(),
            OrganizationLimit::CloudTenants => $organization->cloudTenants()->count(),
            OrganizationLimit::AssetDocuments => $organization->assetDocuments()->count(),
            OrganizationLimit::UserwareAccounts => UserwareAccount::query()
                ->where('organization_id', $organization->id)->count(),
            OrganizationLimit::ApiKeys => $organization->apiKeys()
                ->whereNull('revoked_at')->count(),
        };
    }

    private function assertWithinLimit(
        Organization $organization,
        OrganizationLimit $limit,
        int $currentUsage,
    ): void {
        $limitSource = $organization->package()->first() ?? $organization->plan()->first();
        $maximum = $limitSource?->getAttribute($limit->value);

        if ($maximum === null || $currentUsage < (int) $maximum) {
            return;
        }

        throw ValidationException::withMessages([
            'subscription' => __('The :resource subscription limit of :limit has been reached.', [
                'resource' => $limit->label(),
                'limit' => $maximum,
            ]),
        ]);
    }
}
