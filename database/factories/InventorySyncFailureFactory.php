<?php

namespace Database\Factories;

use App\Models\InventorySyncFailure;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventorySyncFailure>
 */
class InventorySyncFailureFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $deviceName = fake()->domainWord().'-'.fake()->numerify('###');
        $serialNumber = fake()->bothify('SN-????-####');

        return [
            'organization_id' => Organization::factory(),
            'organization_api_key_id' => null,
            'hardware_id' => null,
            'virtualware_id' => null,
            'identity_key' => InventorySyncFailure::identityKey($serialNumber, $deviceName),
            'device_name' => $deviceName,
            'serial_number' => $serialNumber,
            'platform' => 'windows',
            'status_code' => 422,
            'message' => 'The diskEncryption.value.1.state field is required. (and 1 more error)',
            'errors' => [
                'diskEncryption.value.1.state' => ['The diskEncryption.value.1.state field is required.'],
                'diskEncryption.value.1.protectionStatus' => ['The selected diskEncryption.value.1.protectionStatus is invalid.'],
            ],
            'attempt_count' => 1,
            'first_failed_at' => now()->subHour(),
            'last_failed_at' => now()->subHour(),
        ];
    }
}
