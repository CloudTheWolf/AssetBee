<?php

use App\Enums\BitLockerStatus;
use App\Enums\HardwareOperatingSystem;
use App\Models\Hardware;
use App\Models\Organization;
use App\Models\OrganizationApiKey;
use Illuminate\Support\Facades\DB;

test('inventory endpoint requires a valid organization api key', function () {
    $this->postJson('/api/v1/inventory', inventoryPayload())
        ->assertUnauthorized()
        ->assertJsonPath('message', 'A valid organization API key is required.');
});

test('inventory endpoint enforces the version 1 schema', function () {
    $organization = Organization::factory()->create();
    [, $plainTextKey] = OrganizationApiKey::issue($organization, 'Collector');
    $payload = inventoryPayload([
        'deviceName' => ['status' => 0],
    ]);
    unset($payload['manufacturer']);
    $payload['unexpected'] = true;

    $this->withToken($plainTextKey)
        ->postJson('/api/v1/inventory', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['deviceName.status', 'manufacturer', 'unexpected']);
});

test('inventory endpoint creates encrypted organization-scoped hardware', function () {
    $organization = Organization::factory()->create();
    [$apiKey, $plainTextKey] = OrganizationApiKey::issue($organization, 'Collector');

    $this->withToken($plainTextKey)
        ->postJson('/api/v1/inventory', inventoryPayload())
        ->assertCreated()
        ->assertJsonPath('data.name', 'UKMICHAELH25')
        ->assertJsonPath('data.category', 'laptop')
        ->assertJsonPath('data.serialNumber', 'PF5FH9HR')
        ->assertJsonPath('data.manufacturer', 'Lenovo')
        ->assertJsonPath('data.model', 'ThinkPad P1 Gen 7')
        ->assertJsonPath('data.operatingSystem', 'windows_11')
        ->assertJsonMissingPath('data.bitlockerRecoveryKey');

    $hardware = Hardware::query()->sole();

    expect($hardware->organization_id)->toBe($organization->id)
        ->and($hardware->category->value)->toBe('laptop')
        ->and($hardware->manufacturer)->toBe('Lenovo')
        ->and($hardware->model)->toBe('ThinkPad P1 Gen 7')
        ->and($hardware->operating_system)->toBe(HardwareOperatingSystem::Windows11)
        ->and($hardware->cpu)->toBe('Intel(R) Core(TM) Ultra 7 155H')
        ->and($hardware->ram_gb)->toBe(48)
        ->and($hardware->bitlocker_status)->toBe(BitLockerStatus::Enabled)
        ->and($hardware->bitlocker_recovery_key)->toContain('016148-202037')
        ->and($hardware->inventory_payload)->toMatchArray([
            'schemaVersion' => '1.0',
            'platform' => 'windows',
            'type' => 'hardware',
        ])
        ->and(data_get($hardware->inventory_payload, 'sbom.value.format'))->toBe('CycloneDX');

    $storedPayload = DB::table('hardwares')->value('inventory_payload');

    expect($storedPayload)->not->toContain('016148-202037')
        ->and($apiKey->fresh()?->last_used_at)->not->toBeNull();
});

test('inventory endpoint rejects virtualware payloads for now', function () {
    $organization = Organization::factory()->create();
    [, $plainTextKey] = OrganizationApiKey::issue($organization, 'Collector');

    $this->withToken($plainTextKey)
        ->postJson('/api/v1/inventory', inventoryPayload([
            'type' => 'virtualware',
            'hardwareType' => ['status' => 'unavailable', 'value' => null],
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

test('hardware show page displays collected inventory details', function () {
    [, $organization] = actingAsOrganizationMember();
    [, $plainTextKey] = OrganizationApiKey::issue($organization, 'Collector');

    $this->withToken($plainTextKey)
        ->postJson('/api/v1/inventory', inventoryPayload())
        ->assertCreated();

    $hardware = Hardware::query()->sole();

    $this->get(route('assets.hardware.show', $hardware))
        ->assertOk()
        ->assertSee(__('Collected inventory'))
        ->assertSee('Lenovo')
        ->assertSee('ThinkPad P1 Gen 7')
        ->assertSee('WatchGuard EPDR')
        ->assertSee('CCPro Solutions')
        ->assertSee('2026-07 Cumulative Update')
        ->assertSee(__('Software bill of materials'))
        ->assertSee('CycloneDX')
        ->assertSee('8.1.0')
        ->assertSee('application')
        ->assertSee('pkg:generic/WatchGuard%20EPDR@8.1.0')
        ->assertSee(__('Recovery key stored'));
});

test('inventory endpoint updates by serial number within the api key organization', function () {
    $organization = Organization::factory()->create();
    [, $plainTextKey] = OrganizationApiKey::issue($organization, 'Collector');

    $firstResponse = $this->withToken($plainTextKey)
        ->postJson('/api/v1/inventory', inventoryPayload())
        ->assertCreated();

    $this->withToken($plainTextKey)
        ->putJson('/api/v1/inventory', inventoryPayload([
            'collectedAtUtc' => '2026-08-06T11:50:48+00:00',
            'deviceName' => ['status' => 'available', 'value' => 'RENAMED-DEVICE'],
        ]))
        ->assertOk()
        ->assertJsonPath('data.id', $firstResponse->json('data.id'))
        ->assertJsonPath('data.name', 'RENAMED-DEVICE');

    expect(Hardware::query()->count())->toBe(1);
});

test('the same serial number remains isolated between organizations', function () {
    $firstOrganization = Organization::factory()->create();
    $secondOrganization = Organization::factory()->create();
    [, $firstKey] = OrganizationApiKey::issue($firstOrganization, 'First collector');
    [, $secondKey] = OrganizationApiKey::issue($secondOrganization, 'Second collector');

    $this->withToken($firstKey)->postJson('/api/v1/inventory', inventoryPayload())->assertCreated();
    $this->withToken($secondKey)->postJson('/api/v1/inventory', inventoryPayload())->assertCreated();

    expect(Hardware::query()->count())->toBe(2);
});

test('revoked api keys cannot submit inventory', function () {
    $organization = Organization::factory()->create();
    [$apiKey, $plainTextKey] = OrganizationApiKey::issue($organization, 'Collector');
    $apiKey->update(['revoked_at' => now()]);

    $this->withToken($plainTextKey)
        ->postJson('/api/v1/inventory', inventoryPayload())
        ->assertUnauthorized();
});
