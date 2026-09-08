<?php

namespace Database\Factories;

use App\Models\Software;
use App\Models\SoftwareKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SoftwareKey>
 */
class SoftwareKeyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'software_id' => Software::factory()->keyBased(),
            'value' => strtoupper(fake()->bothify('????-????-????-????')),
            'label' => fake()->optional()->words(2, true),
        ];
    }
}
