<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationGoogleDomain;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
        ];
    }

    /**
     * @param  string|array<int, string>  $domains
     */
    public function withGoogleDomains(string|array $domains, bool $verified = true): static
    {
        $domains = is_array($domains) ? $domains : [$domains];

        return $this->afterCreating(function (Organization $organization) use ($domains, $verified): void {
            foreach ($domains as $domain) {
                OrganizationGoogleDomain::factory()
                    ->for($organization)
                    ->state([
                        'domain' => Str::lower($domain),
                        'verified_at' => $verified ? now() : null,
                    ])
                    ->create();
            }
        });
    }

    /**
     * @param  string|array<int, string>  $domains
     */
    public function withUnverifiedGoogleDomains(string|array $domains): static
    {
        return $this->withGoogleDomains($domains, verified: false);
    }

    /**
     * @deprecated Use withGoogleDomains()
     */
    public function withGoogleDomain(string $domain): static
    {
        return $this->withGoogleDomains($domain);
    }
}
