<?php

namespace App\Actions\Subscriptions;

use App\Models\Organization;
use App\Models\SubscriptionPackage;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Checkout;

class CreateOrganizationCheckout
{
    /** @throws ValidationException */
    public function handle(Organization $organization, SubscriptionPackage $package): Checkout
    {
        if ($organization->subscribed('default')) {
            throw ValidationException::withMessages([
                'subscription' => __('This organization already has an active subscription.'),
            ]);
        }

        if (! $package->is_active) {
            throw ValidationException::withMessages([
                'subscription' => __('This subscription package is not available.'),
            ]);
        }

        return $organization
            ->newSubscription('default', $package->stripe_price_id)
            ->withMetadata(['subscription_package_id' => (string) $package->id])
            ->checkout([
                'success_url' => route('organizations.billing.success'),
                'cancel_url' => route('organizations.billing.cancel'),
            ]);
    }
}
