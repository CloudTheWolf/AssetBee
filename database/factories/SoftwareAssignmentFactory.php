<?php

namespace Database\Factories;

use App\Models\Software;
use App\Models\SoftwareAssignment;
use App\Models\Userware;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SoftwareAssignment>
 */
class SoftwareAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'software_id' => Software::factory(),
            'userware_id' => Userware::factory(),
            'assigned_at' => now(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
