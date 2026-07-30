<?php

namespace Database\Factories;

use App\Enums\VirtualwareCategory;
use App\Enums\VirtualwareProvider;
use App\Enums\VirtualwareStatus;
use App\Models\Organization;
use App\Models\Virtualware;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Virtualware>
 */
class VirtualwareFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->domainWord().'-vm-'.fake()->numerify('##'),
            'provider' => fake()->randomElement(VirtualwareProvider::cases()),
            'external_id' => fake()->optional()->uuid(),
            'category' => fake()->randomElement(VirtualwareCategory::cases()),
            'status' => VirtualwareStatus::Running,
            'host_hardware_id' => null,
            'assigned_userware_id' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
