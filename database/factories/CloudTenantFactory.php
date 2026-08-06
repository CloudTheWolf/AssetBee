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

    public function aws(): static
    {
        return $this->state(fn (): array => [
            'provider' => CloudTenantProvider::Aws,
            'domain' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function withCredentials(array $credentials = []): static
    {
        return $this->state(fn (array $attributes): array => [
            'credentials' => $credentials !== [] ? $credentials : match ($attributes['provider'] ?? null) {
                CloudTenantProvider::Aws, CloudTenantProvider::Aws->value => [
                    'access_key_id' => 'AKIAEXAMPLEKEY1234',
                    'secret_access_key' => 'secret-example-key',
                    'region' => 'eu-west-1',
                ],
                CloudTenantProvider::Azure, CloudTenantProvider::Azure->value => [
                    'tenant_id' => fake()->uuid(),
                    'client_id' => fake()->uuid(),
                    'client_secret' => 'azure-secret',
                    'subscription_id' => fake()->uuid(),
                ],
                CloudTenantProvider::Gcp, CloudTenantProvider::Gcp->value => [
                    'project_id' => 'example-project',
                    'service_account_json' => '{"type":"service_account"}',
                ],
                default => [
                    'access_key_id' => 'AKIAEXAMPLEKEY1234',
                    'secret_access_key' => 'secret-example-key',
                    'region' => 'eu-west-1',
                ],
            },
        ]);
    }
}
