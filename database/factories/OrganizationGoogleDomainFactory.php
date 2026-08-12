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
            'verification_token' => OrganizationGoogleDomain::generateVerificationToken(),
            'verified_at' => null,
            'verification_last_checked_at' => null,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (): array => [
            'verified_at' => now(),
        ]);
    }
}
