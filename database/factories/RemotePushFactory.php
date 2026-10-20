<?php

namespace Database\Factories;

use App\Enums\RemotePushStatus;
use App\Enums\RemotePushType;
use App\Models\Organization;
use App\Models\RemotePush;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RemotePush>
 */
class RemotePushFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
            'type' => RemotePushType::BitlockerStatus,
            'remote_package_id' => null,
            'status' => RemotePushStatus::Open,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
