<?php

namespace App\Actions\Subscriptions;

use App\Enums\OrganizationLimit;
use App\Enums\SubscriptionBillingInterval;
use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateOrganizationSubscription
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Organization $organization, array $input): OrganizationSubscription
    {
        $limitRules = collect(OrganizationLimit::cases())
            ->mapWithKeys(fn (OrganizationLimit $limit) => [
                $limit->value => ['nullable', 'integer', 'min:0'],
            ])
            ->all();

        $validated = Validator::make($input, [
            'plan_name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::enum(SubscriptionStatus::class)],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'currency' => ['required', 'string', 'alpha', 'size:3'],
            'billing_interval' => ['required', Rule::enum(SubscriptionBillingInterval::class)],
            'stripe_price_id' => ['nullable', 'string', 'max:255', 'regex:/^price_[A-Za-z0-9]+$/'],
            'renews_at' => ['nullable', 'date'],
            ...$limitRules,
        ])->validate();

        $validated['currency'] = strtoupper($validated['currency']);

        return $organization->plan()->updateOrCreate([], $validated);
    }
}
