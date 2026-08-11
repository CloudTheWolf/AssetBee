<?php

namespace App\Http\Controllers;

use App\Actions\Subscriptions\CreateOrganizationBillingPortal;
use App\Actions\Subscriptions\CreateOrganizationCheckout;
use App\Models\SubscriptionPackage;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Laravel\Cashier\Checkout;

class OrganizationBillingController extends Controller
{
    public function checkout(
        SubscriptionPackage $package,
        CreateOrganizationCheckout $createCheckout,
    ): Checkout {
        $organization = CurrentOrganization::require();
        Gate::authorize('manageBilling', $organization);

        return $createCheckout->handle($organization, $package);
    }

    public function portal(CreateOrganizationBillingPortal $createPortal): RedirectResponse
    {
        $organization = CurrentOrganization::require();
        Gate::authorize('manageBilling', $organization);

        return redirect()->away($createPortal->handle($organization), 303);
    }

    public function success(): RedirectResponse
    {
        $organization = CurrentOrganization::require();
        Gate::authorize('manageBilling', $organization);

        return redirect()->route('organizations.billing')
            ->with('status', __('Subscription checkout completed. Stripe may take a moment to confirm it.'));
    }

    public function cancel(): RedirectResponse
    {
        $organization = CurrentOrganization::require();
        Gate::authorize('manageBilling', $organization);

        return redirect()->route('organizations.billing')
            ->with('status', __('Subscription checkout was cancelled.'));
    }
}
