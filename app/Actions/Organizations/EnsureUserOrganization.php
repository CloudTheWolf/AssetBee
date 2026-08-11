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
     * Attach the user to an organization based on Google Workspace domain, or leave unchanged.
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

        $organization = Organization::findByGoogleDomain($emailDomain);

        if ($organization === null) {
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
        } else {
            app(OrganizationSubscriptionLimits::class)->assertCanAddMember($organization);

            $organization->users()->syncWithoutDetaching([
                $user->id => ['role' => OrganizationRole::Member->value],
            ]);
        }

        CurrentOrganization::set($organization, $user);

        return $organization;
    }
}
