<?php

namespace Database\Factories;

use App\Enums\HardwareCategory;
use App\Enums\HardwareStatus;
use App\Models\Hardware;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Hardware>
 */
class HardwareFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(3, true),
            'asset_tag' => 'HW-'.fake()->unique()->numerify('####'),
            'serial_number' => fake()->optional()->bothify('SN-????-####'),
            'manufacturer' => fake()->optional()->company(),
            'model' => fake()->optional()->bothify('Model-##??'),
            'operating_system' => null,
            'cpu' => null,
            'ram_gb' => null,
            'storage_gb' => null,
            'bitlocker_status' => null,
            'bitlocker_recovery_key' => null,
            'is_vm_host' => false,
            'category' => fake()->randomElement(HardwareCategory::cases()),
            'status' => HardwareStatus::Available,
            'assigned_userware_id' => null,
            'purchased_at' => fake()->optional()->date(),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function server(bool $vmHost = false): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => HardwareCategory::Server,
            'is_vm_host' => $vmHost,
            'name' => fake()->domainWord().'-srv-'.fake()->numerify('##'),
        ]);
    }

    public function vmHost(): static
    {
        return $this->server(vmHost: true);
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function withProxmoxCredentials(array $credentials = []): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => HardwareCategory::Server,
            'is_vm_host' => true,
            'proxmox_credentials' => [
                'api_url' => $credentials['api_url'] ?? 'https://pve.example:8006',
                'token_id' => $credentials['token_id'] ?? 'assetbee@pve!inventory',
                'token_secret' => $credentials['token_secret'] ?? 'secret-token',
                'verify_tls' => $credentials['verify_tls'] ?? true,
                'node' => array_key_exists('node', $credentials) ? $credentials['node'] : 'pve1',
            ],
        ]);
    }
}
