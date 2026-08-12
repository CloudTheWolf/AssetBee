<?php

use App\Enums\BitLockerStatus;
use App\Enums\HardwareCategory;
use App\Enums\HardwareOperatingSystem;
use App\Enums\HardwareStatus;
use App\Models\Hardware;
use App\Models\Organization;
use App\Models\OrganizationApiKey;

test('soc2 audit endpoints require a valid organization api key', function () {
    $this->getJson('/api/v1/audit/soc2')
        ->assertUnauthorized();

    $this->get('/api/v1/audit/soc2.pdf')
        ->assertUnauthorized();
});

test('soc2 audit json endpoint returns programmatic control evidence', function () {
    $organization = Organization::factory()->create();
    [, $plainTextKey] = OrganizationApiKey::issue($organization, 'Auditor');

    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'UKMICHAELH25',
        'serial_number' => 'PF5FH9HR',
        'category' => HardwareCategory::Laptop,
        'status' => HardwareStatus::Available,
        'operating_system' => HardwareOperatingSystem::Windows11,
        'bitlocker_status' => BitLockerStatus::Enabled,
        'bitlocker_recovery_key' => '016148-202037-546898-706926-484627-138688-405482-446105',
        'inventory_collected_at' => now('UTC')->subDay(),
        'inventory_payload' => inventoryPayload(),
    ]);

    $this->withToken($plainTextKey)
        ->getJson('/api/v1/audit/soc2')
        ->assertOk()
        ->assertJsonPath('data.reportType', 'soc2_inventory_controls')
        ->assertJsonPath('data.organization.id', $organization->id)
        ->assertJsonPath('data.summary.deviceCount', 1)
        ->assertJsonPath('data.devices.0.serialNumber', 'PF5FH9HR')
        ->assertJsonPath('data.devices.0.sbom.componentCount', 1)
        ->assertJsonPath('data.devices.0.encryption.recoveryKeyStored', true)
        ->assertJsonMissingPath('data.devices.0.encryption.recoveryKey');
});

test('soc2 audit pdf endpoint returns a downloadable pdf', function () {
    $organization = Organization::factory()->create(['slug' => 'acme-corp']);
    [, $plainTextKey] = OrganizationApiKey::issue($organization, 'Auditor');

    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'inventory_collected_at' => now('UTC')->subDay(),
        'inventory_payload' => inventoryPayload(),
    ]);

    $response = $this->withToken($plainTextKey)
        ->get('/api/v1/audit/soc2.pdf')
        ->assertOk();

    expect($response->headers->get('content-type'))->toStartWith('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('soc2-audit-acme-corp')
        ->and($response->streamedContent())->toStartWith('%PDF-1.4');
});
