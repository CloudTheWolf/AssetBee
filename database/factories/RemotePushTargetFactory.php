<?php

namespace Database\Factories;

use App\Enums\RemotePushTargetStatus;
use App\Models\Hardware;
use App\Models\RemotePush;
use App\Models\RemotePushTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RemotePushTarget>
 */
class RemotePushTargetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'remote_push_id' => RemotePush::factory(),
            'hardware_id' => Hardware::factory(),
            'status' => RemotePushTargetStatus::Pending,
            'first_notified_at' => null,
            'postpone_deadline_at' => null,
            'scheduled_for' => null,
            'result_json' => null,
            'error' => null,
            'delivered_at' => null,
            'completed_at' => null,
        ];
    }
}
