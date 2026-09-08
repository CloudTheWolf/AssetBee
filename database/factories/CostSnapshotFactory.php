<?php

namespace Database\Factories;

use App\Enums\CostSyncSource;
use App\Models\CostSnapshot;
use App\Models\Organization;
use App\Models\Software;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CostSnapshot>
 */
class CostSnapshotFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $periodStart = now()->startOfMonth()->subMonth()->toDateString();
        $periodEnd = now()->startOfMonth()->subDay()->toDateString();

        return [
            'organization_id' => Organization::factory(),
            'costable_type' => Software::class,
            'costable_id' => Software::factory(),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'amount' => fake()->randomFloat(2, 10, 5000),
            'currency' => 'USD',
            'seat_count' => fake()->optional()->numberBetween(1, 100),
            'provider' => fake()->randomElement(CostSyncSource::cases()),
            'meta' => null,
            'synced_at' => now(),
        ];
    }
}
