<?php

use App\Models\Organization;
use App\Models\OrganizationApiKey;

test('drone settings endpoint requires a valid organization api key', function () {
    $this->getJson('/api/v1/drone/settings')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'A valid organization API key is required.');
});

test('drone settings endpoint returns enableDriveEncryption false by default', function () {
    $organization = Organization::factory()->create();
    [, $plainTextKey] = OrganizationApiKey::issue($organization, 'Collector');

    $this->withToken($plainTextKey)
        ->getJson('/api/v1/drone/settings')
        ->assertOk()
        ->assertExactJson([
            'enableDriveEncryption' => false,
        ]);
});

test('drone settings endpoint returns enableDriveEncryption when enabled', function () {
    $organization = Organization::factory()->withDriveEncryption()->create();
    [, $plainTextKey] = OrganizationApiKey::issue($organization, 'Collector');

    $this->withToken($plainTextKey)
        ->getJson('/api/v1/drone/settings')
        ->assertOk()
        ->assertExactJson([
            'enableDriveEncryption' => true,
        ]);
});

test('inventory response includes drone enableDriveEncryption setting', function () {
    $organization = Organization::factory()->withDriveEncryption()->create();
    [, $plainTextKey] = OrganizationApiKey::issue($organization, 'Collector');

    $this->withToken($plainTextKey)
        ->postJson('/api/v1/inventory', inventoryPayload([
            'deviceName' => ['status' => 'available', 'value' => 'WIN-TEST'],
            'serialNumber' => ['status' => 'available', 'value' => 'ABC123'],
        ]))
        ->assertCreated()
        ->assertJsonPath('drone.enableDriveEncryption', true);
});
