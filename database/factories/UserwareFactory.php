<?php

namespace Database\Factories;

use App\Enums\UserwareStatus;
use App\Models\Organization;
use App\Models\Userware;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Userware>
 */
class UserwareFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'employee_id' => fake()->optional()->numerify('E-####'),
            'department' => fake()->optional()->randomElement(['Engineering', 'IT', 'HR', 'Finance', 'Sales']),
            'status' => UserwareStatus::Active,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
