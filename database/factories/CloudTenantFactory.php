<?php

namespace Database\Factories;

use App\Enums\CloudTenantProvider;
use App\Enums\CloudTenantStatus;
use App\Models\CloudTenant;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CloudTenant>
 */
class CloudTenantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->company().' Tenant',
            'provider' => fake()->randomElement(CloudTenantProvider::cases()),
            'external_id' => fake()->optional()->uuid(),
            'domain' => fake()->optional()->domainName(),
            'status' => CloudTenantStatus::Active,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
