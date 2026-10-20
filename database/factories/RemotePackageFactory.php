<?php

namespace Database\Factories;

use App\Enums\RemotePackageSource;
use App\Models\Organization;
use App\Models\RemotePackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RemotePackage>
 */
class RemotePackageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->words(2, true),
            'source' => RemotePackageSource::Winget,
            'winget_id' => 'Example.Package',
            'msi_url' => null,
            'msi_args' => null,
            'choco_id' => null,
            'silent_args' => null,
            'sha256' => null,
        ];
    }

    public function msi(?string $url = null): static
    {
        return $this->state(fn (): array => [
            'source' => RemotePackageSource::Msi,
            'msi_url' => $url ?? 'https://example.com/package.msi',
            'winget_id' => null,
            'choco_id' => null,
        ]);
    }

    public function choco(string $id = 'example'): static
    {
        return $this->state(fn (): array => [
            'source' => RemotePackageSource::Choco,
            'choco_id' => $id,
            'winget_id' => null,
            'msi_url' => null,
        ]);
    }
}
