<?php

namespace Database\Factories;

use App\Enums\SubscriptionBillingInterval;
use App\Models\SubscriptionPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPackage>
 */
class SubscriptionPackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'is_active' => true,
            'price' => fake()->randomFloat(2, 10, 500),
            'currency' => 'GBP',
            'billing_interval' => SubscriptionBillingInterval::Monthly,
            'stripe_price_id' => 'price_'.fake()->unique()->regexify('[A-Za-z0-9]{16}'),
            'sort_order' => 0,
            'member_limit' => null,
            'userware_limit' => null,
            'hardware_limit' => null,
            'virtualware_limit' => null,
            'software_limit' => null,
            'cloud_tenant_limit' => null,
            'asset_document_limit' => null,
            'userware_account_limit' => null,
            'api_key_limit' => null,
        ];
    }
}
