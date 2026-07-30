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
            'category' => fake()->randomElement(HardwareCategory::cases()),
            'status' => HardwareStatus::Available,
            'assigned_userware_id' => null,
            'purchased_at' => fake()->optional()->date(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
