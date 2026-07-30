<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationGoogleDomain;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OrganizationGoogleDomain>
 */
class OrganizationGoogleDomainFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'domain' => Str::lower(fake()->unique()->domainName()),
        ];
    }
}
