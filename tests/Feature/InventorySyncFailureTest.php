<?php

use App\Enums\HardwareCategory;
use App\Enums\InventoryReport;
use App\Enums\OrganizationRole;
use App\Models\Hardware;
use App\Models\InventorySyncFailure;
use App\Models\Organization;
use App\Models\OrganizationApiKey;
use Livewire\Livewire;

test('rejected inventory syncs are recorded for the organization', function () {
    $organization = Organization::factory()->create();
    $hardware = Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'WIN-FAIL',
        'serial_number' => 'FAIL-SERIAL',
        'category' => HardwareCategory::Laptop,
    ]);
    [, $plainTextKey] = OrganizationApiKey::issue($organization, 'Collector');

    $payload = inventoryPayload([
        'deviceName' => ['status' => 'available', 'value' => 'WIN-FAIL'],
        'serialNumber' => ['status' => 'available', 'value' => 'FAIL-SERIAL'],
        'diskEncryption' => [
            'status' => 'available',
            'value' => [
                [
                    'volume' => 'C:',
                    'technology' => 'BitLocker',
                    'state' => 'FullyEncrypted',
                    'recoveryKeys' => [],
                    'keyProtectors' => [],
                    'protectionStatus' => 'Maybe',
                ],
            ],
        ],
    ]);

    $this->withToken($plainTextKey)
        ->postJson('/api/v1/inventory', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'diskEncryption.value.0.protectionStatus',
        ]);

    $failure = InventorySyncFailure::query()->sole();

    expect($failure->organization_id)->toBe($organization->id)
        ->and($failure->hardware_id)->toBe($hardware->id)
        ->and($failure->device_name)->toBe('WIN-FAIL')
        ->and($failure->serial_number)->toBe('FAIL-SERIAL')
        ->and($failure->status_code)->toBe(422)
        ->and($failure->attempt_count)->toBe(1)
        ->and($failure->errors)->toHaveKey('diskEncryption.value.0.protectionStatus');

    $this->withToken($plainTextKey)
        ->postJson('/api/v1/inventory', $payload)
        ->assertUnprocessable();

    expect($failure->fresh()->attempt_count)->toBe(2);
});

test('successful inventory sync clears matching failure records', function () {
    $organization = Organization::factory()->create();
    $hardware = Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'WIN-OK',
        'serial_number' => 'OK-SERIAL',
        'category' => HardwareCategory::Laptop,
    ]);
    [, $plainTextKey] = OrganizationApiKey::issue($organization, 'Collector');

    InventorySyncFailure::factory()->create([
        'organization_id' => $organization->id,
        'hardware_id' => $hardware->id,
        'identity_key' => InventorySyncFailure::identityKey('OK-SERIAL', 'WIN-OK'),
        'device_name' => 'WIN-OK',
        'serial_number' => 'OK-SERIAL',
    ]);

    $this->withToken($plainTextKey)
        ->postJson('/api/v1/inventory', inventoryPayload([
            'deviceName' => ['status' => 'available', 'value' => 'WIN-OK'],
            'serialNumber' => ['status' => 'available', 'value' => 'OK-SERIAL'],
        ]))
        ->assertOk();

    expect(InventorySyncFailure::query()->count())->toBe(0);
});

test('failed inventory syncs appear in the report and on the hardware page', function () {
    [, $organization] = actingAsOrganizationMember(OrganizationRole::Admin);

    $hardware = Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Broken Sync Laptop',
        'serial_number' => 'BROKEN-1',
        'category' => HardwareCategory::Laptop,
    ]);

    InventorySyncFailure::factory()->create([
        'organization_id' => $organization->id,
        'hardware_id' => $hardware->id,
        'identity_key' => InventorySyncFailure::identityKey('BROKEN-1', 'Broken Sync Laptop'),
        'device_name' => 'Broken Sync Laptop',
        'serial_number' => 'BROKEN-1',
        'message' => 'The diskEncryption.value.1.state field is required. (and 1 more error)',
        'attempt_count' => 3,
    ]);

    $this->get(route('reports.index'))
        ->assertOk()
        ->assertSee(__('Failed Drone syncs'));

    $this->get(route('reports.show', InventoryReport::FailedInventorySyncs->value))
        ->assertOk()
        ->assertSee('Broken Sync Laptop')
        ->assertSee('BROKEN-1')
        ->assertSee('HTTP 422');

    Livewire::test('pages::assets.hardware.show', ['hardware' => $hardware])
        ->assertOk()
        ->assertSee(__('Latest Drone sync failed'))
        ->assertSee('The diskEncryption.value.1.state field is required');
});
