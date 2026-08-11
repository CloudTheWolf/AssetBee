<?php

namespace App\Actions\Subscriptions;

use App\Enums\OrganizationLimit;
use App\Enums\SubscriptionBillingInterval;
use App\Models\SubscriptionPackage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveSubscriptionPackage
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(?SubscriptionPackage $package, array $input): SubscriptionPackage
    {
        $limitRules = collect(OrganizationLimit::cases())
            ->mapWithKeys(fn (OrganizationLimit $limit) => [
                $limit->value => ['nullable', 'integer', 'min:0'],
            ])
            ->all();

        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'currency' => ['required', 'string', 'alpha', 'size:3'],
            'billing_interval' => ['required', Rule::enum(SubscriptionBillingInterval::class)],
            'stripe_price_id' => [
                'required',
                'string',
                'max:255',
                'regex:/^price_[A-Za-z0-9]+$/',
                Rule::unique(SubscriptionPackage::class)->ignore($package),
            ],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            ...$limitRules,
        ])->validate();

        $validated['currency'] = strtoupper($validated['currency']);

        $package ??= new SubscriptionPackage;
        $package->fill($validated)->save();

        return $package;
    }
}
