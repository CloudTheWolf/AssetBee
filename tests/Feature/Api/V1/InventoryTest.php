<?php

use App\Enums\BitLockerStatus;
use App\Enums\HardwareOperatingSystem;
use App\Models\Hardware;
use App\Models\Organization;
use App\Models\OrganizationApiKey;
use Illuminate\Support\Facades\DB;

function inventoryPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'schemaVersion' => '1.0',
        'collectedAtUtc' => '2026-08-06T10:50:48.6327507+00:00',
        'platform' => 'windows',
        'type' => 'hardware',
        'hardwareType' => ['status' => 'available', 'value' => 'laptop'],
        'deviceName' => ['status' => 'available', 'value' => 'UKMICHAELH25'],
        'serialNumber' => ['status' => 'available', 'value' => 'PF5FH9HR'],
        'manufacturer' => ['status' => 'available', 'value' => 'Lenovo'],
        'model' => ['status' => 'available', 'value' => 'ThinkPad P1 Gen 7'],
        'operatingSystem' => [
            'status' => 'available',
            'value' => [
                'name' => 'Microsoft Windows 11 Pro',
                'version' => '10.0.26200',
                'displayVersion' => '24H2',
                'build' => '26200',
                'kernel' => null,
            ],
        ],
        'cpu' => [
            'status' => 'available',
            'value' => [
                'model' => 'Intel(R) Core(TM) Ultra 7 155H',
                'logicalProcessors' => 22,
                'physicalCores' => 16,
            ],
        ],
        'memory' => ['status' => 'available', 'value' => ['totalBytes' => 51012263936]],
        'disks' => [
            'status' => 'available',
            'value' => [
                [
                    'name' => 'C:',
                    'mountPoint' => 'C:',
                    'totalBytes' => 1021821579264,
                    'availableBytes' => 470782820352,
                    'fileSystem' => 'NTFS',
                ],
            ],
        ],
        'diskEncryption' => [
            'status' => 'available',
            'value' => [
                [
                    'volume' => 'C:',
                    'technology' => 'BitLocker',
                    'state' => 'FullyEncrypted',
                    'recoveryKeys' => [],
                    'keyProtectors' => [
                        [
                            'keyProtectorId' => '{A8414EAF-085F-4A35-AB76-D4F4B88B5607}',
                            'recoveryKey' => '016148-202037-546898-706926-484627-138688-405482-446105',
                        ],
                    ],
                ],
            ],
        ],
        'domainWorkspace' => [
            'status' => 'available',
            'value' => [
                'domain' => 'WORKGROUP',
                'domainJoined' => false,
                'workspace' => 'CCPro Solutions',
                'workspaceJoined' => true,
            ],
        ],
        'loginProviders' => [
            'status' => 'available',
            'value' => [
                ['name' => 'Windows Credential Provider', 'state' => 'available'],
            ],
        ],
        'antivirus' => [
            'status' => 'available',
            'value' => [
                [
                    'name' => 'WatchGuard EPDR',
                    'state' => 'enabled',
                    'enabled' => true,
                    'upToDate' => true,
                    'detail' => 'Security Center state 0x071000',
                ],
            ],
        ],
        'updates' => [
            'status' => 'available',
            'value' => [
                'installed' => [
                    [
                        'id' => 'KB5062660',
                        'title' => '2026-07 Cumulative Update',
                        'category' => 'Security Updates',
                        'installedAtUtc' => '2026-08-01T10:00:00+00:00',
                        'kbArticle' => 'KB5062660',
                    ],
                ],
                'available' => [],
            ],
        ],
    ], $overrides);
}

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
        ]);

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
