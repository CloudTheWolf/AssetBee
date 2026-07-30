<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Software;
use App\Models\Userware;
use App\Models\UserwareAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserwareAccount>
 */
class UserwareAccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'userware_id' => Userware::factory(),
            'software_id' => null,
            'site_name' => fake()->company().' Portal',
            'site_url' => fake()->url(),
            'username' => fake()->optional()->userName(),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function forSoftware(?Software $software = null): static
    {
        return $this->state(function (array $attributes) use ($software) {
            $software ??= Software::factory()->create([
                'organization_id' => $attributes['organization_id'],
            ]);

            return [
                'organization_id' => $software->organization_id,
                'software_id' => $software->id,
                'site_name' => null,
                'site_url' => null,
            ];
        });
    }

    public function forExternalSite(string $name = 'GitHub', string $url = 'https://github.com'): static
    {
        return $this->state(fn (array $attributes) => [
            'software_id' => null,
            'site_name' => $name,
            'site_url' => $url,
        ]);
    }
}
