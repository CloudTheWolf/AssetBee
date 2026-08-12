<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use App\Support\CurrentOrganization;
use App\Support\OrganizationSubscriptionLimits;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EnsureUserOrganization
{
    public function __construct(
        private SyncOrganizationGoogleDomains $syncOrganizationGoogleDomains,
    ) {}

    /**
     * Attach the user to an organization based on a verified Google Workspace domain, or leave unchanged.
     */
    public function handle(User $user, ?string $emailDomain = null): ?Organization
    {
        if (! $user->isCustomer()) {
            return null;
        }

        if ($user->organizations()->exists()) {
            return CurrentOrganization::ensureSelected($user);
        }

        $emailDomain ??= Str::lower(Str::after($user->email, '@'));

        if ($emailDomain === '') {
            return null;
        }

        $organization = Organization::findByGoogleDomain($emailDomain, verifiedOnly: true);

        if ($organization !== null) {
            app(OrganizationSubscriptionLimits::class)->assertCanAddMember($organization);

            $organization->users()->syncWithoutDetaching([
                $user->id => ['role' => OrganizationRole::Member->value],
            ]);

            CurrentOrganization::set($organization, $user);

            return $organization;
        }

        // Domain already claimed (even if unverified) — do not auto-create a competing org.
        if (Organization::findByGoogleDomain($emailDomain, verifiedOnly: false) !== null) {
            return null;
        }

        $allowedDomains = config('services.google.hosted_domains', []);

        if (! in_array($emailDomain, $allowedDomains, true)) {
            return null;
        }

        $organization = DB::transaction(function () use ($emailDomain, $user) {
            $organization = Organization::create([
                'name' => Str::headline($emailDomain),
                'slug' => Str::slug($emailDomain),
            ]);

            $this->syncOrganizationGoogleDomains->handle($organization, [$emailDomain]);

            $organization->users()->attach($user->id, [
                'role' => OrganizationRole::Owner->value,
            ]);

            return $organization;
        });

        CurrentOrganization::set($organization, $user);

        return $organization;
    }
}
