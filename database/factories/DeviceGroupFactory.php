<?php

namespace Database\Factories;

use App\Models\DeviceGroup;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceGroup>
 */
class DeviceGroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->words(2, true).' group',
            'description' => fake()->optional()->sentence(),
        ];
    }
}
