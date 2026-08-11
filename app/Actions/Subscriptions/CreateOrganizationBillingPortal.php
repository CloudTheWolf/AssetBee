<?php

namespace App\Actions\Subscriptions;

use App\Models\Organization;
use Illuminate\Validation\ValidationException;

class CreateOrganizationBillingPortal
{
    /** @throws ValidationException */
    public function handle(Organization $organization): string
    {
        if (! $organization->hasStripeId()) {
            throw ValidationException::withMessages([
                'subscription' => __('This organization does not have a Stripe billing account yet.'),
            ]);
        }

        return $organization->billingPortalUrl(route('organizations.billing'));
    }
}
