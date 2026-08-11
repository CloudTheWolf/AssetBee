<?php

namespace Database\Factories;

use App\Enums\SubscriptionBillingInterval;
use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationSubscription>
 */
class OrganizationSubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'plan_name' => 'Custom',
            'status' => SubscriptionStatus::Active,
            'price' => 0,
            'currency' => 'GBP',
            'billing_interval' => SubscriptionBillingInterval::Monthly,
            'stripe_price_id' => null,
            'renews_at' => null,
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
