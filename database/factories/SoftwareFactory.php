<?php

namespace Database\Factories;

use App\Enums\SoftwareLicenseType;
use App\Enums\SoftwareStatus;
use App\Models\Organization;
use App\Models\Software;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Software>
 */
class SoftwareFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $licenseType = fake()->randomElement(SoftwareLicenseType::cases());

        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->word().' '.fake()->word().' License',
            'vendor' => fake()->optional()->company(),
            'license_type' => $licenseType,
            'total_seats' => $licenseType === SoftwareLicenseType::Seat ? fake()->numberBetween(5, 100) : null,
            'status' => SoftwareStatus::Active,
            'expires_at' => fake()->optional()->dateTimeBetween('now', '+2 years'),
            'is_recurring' => false,
            'billing_interval' => null,
            'billing_amount' => null,
            'currency' => 'GBP',
            'next_billing_at' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function seatBased(int $seats = 10): static
    {
        return $this->state(fn (array $attributes) => [
            'license_type' => SoftwareLicenseType::Seat,
            'total_seats' => $seats,
        ]);
    }

    public function recurring(string $interval = 'monthly', float $amount = 99.00): static
    {
        return $this->state(fn (array $attributes) => [
            'license_type' => SoftwareLicenseType::Subscription,
            'is_recurring' => true,
            'billing_interval' => $interval,
            'billing_amount' => $amount,
            'currency' => 'GBP',
            'next_billing_at' => now()->addMonth()->toDateString(),
        ]);
    }
}
