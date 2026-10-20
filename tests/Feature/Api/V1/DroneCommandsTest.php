<?php

use App\Enums\RemotePushTargetStatus;
use App\Enums\RemotePushType;
use App\Models\Hardware;
use App\Models\Organization;
use App\Models\OrganizationApiKey;
use App\Models\RemotePackage;
use App\Models\RemotePush;
use App\Models\RemotePushTarget;

test('drone commands endpoint requires api key and returns pending targets by serial', function () {
    $organization = Organization::factory()->create();
    [, $plainTextKey] = OrganizationApiKey::issue($organization, 'Collector');
    $hardware = Hardware::factory()->create([
        'organization_id' => $organization->id,
        'serial_number' => 'SN-CMD-1',
        'name' => 'WIN-CMD',
    ]);
    $package = RemotePackage::factory()->create(['organization_id' => $organization->id]);
    $push = RemotePush::factory()->create([
        'organization_id' => $organization->id,
        'type' => RemotePushType::InstallPackage,
        'remote_package_id' => $package->id,
    ]);
    $target = RemotePushTarget::factory()->create([
        'remote_push_id' => $push->id,
        'hardware_id' => $hardware->id,
        'status' => RemotePushTargetStatus::Pending,
    ]);

    $this->getJson('/api/v1/drone/commands?serialNumber=SN-CMD-1')
        ->assertUnauthorized();

    $this->withToken($plainTextKey)
        ->getJson('/api/v1/drone/commands?serialNumber=SN-CMD-1')
        ->assertOk()
        ->assertJsonPath('hardwareId', $hardware->id)
        ->assertJsonPath('data.0.id', $target->id)
        ->assertJsonPath('data.0.type', 'install_package')
        ->assertJsonPath('data.0.requiresUserConsent', true)
        ->assertJsonPath('data.0.package.name', $package->name);
});

test('drone command status enforces postpone deadline', function () {
    $organization = Organization::factory()->create();
    [, $plainTextKey] = OrganizationApiKey::issue($organization, 'Collector');
    $hardware = Hardware::factory()->create(['organization_id' => $organization->id]);
    $push = RemotePush::factory()->create([
        'organization_id' => $organization->id,
        'type' => RemotePushType::InstallPackage,
        'remote_package_id' => RemotePackage::factory()->create(['organization_id' => $organization->id])->id,
    ]);
    $target = RemotePushTarget::factory()->create([
        'remote_push_id' => $push->id,
        'hardware_id' => $hardware->id,
        'status' => RemotePushTargetStatus::AwaitingUser,
        'first_notified_at' => now()->subHours(2),
        'postpone_deadline_at' => now()->addHours(22),
    ]);

    $this->withToken($plainTextKey)
        ->postJson("/api/v1/drone/commands/{$target->id}/status", [
            'status' => 'scheduled',
            'scheduledFor' => now()->addDays(2)->toIso8601String(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['scheduledFor']);

    $this->withToken($plainTextKey)
        ->postJson("/api/v1/drone/commands/{$target->id}/status", [
            'status' => 'scheduled',
            'scheduledFor' => now()->addHours(1)->toIso8601String(),
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'scheduled');

    expect($target->fresh()->status)->toBe(RemotePushTargetStatus::Scheduled);
});

test('drone command verify authorizes assigned device and rejects others', function () {
    $organization = Organization::factory()->create();
    [, $plainTextKey] = OrganizationApiKey::issue($organization, 'Collector');
    $hardware = Hardware::factory()->create([
        'organization_id' => $organization->id,
        'serial_number' => 'SN-VERIFY-1',
        'name' => 'WIN-VERIFY',
    ]);
    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'serial_number' => 'SN-OTHER',
        'name' => 'WIN-OTHER',
    ]);
    $package = RemotePackage::factory()->create(['organization_id' => $organization->id]);
    $push = RemotePush::factory()->create([
        'organization_id' => $organization->id,
        'type' => RemotePushType::InstallPackage,
        'remote_package_id' => $package->id,
    ]);
    $target = RemotePushTarget::factory()->create([
        'remote_push_id' => $push->id,
        'hardware_id' => $hardware->id,
        'status' => RemotePushTargetStatus::AwaitingUser,
    ]);

    $this->withToken($plainTextKey)
        ->postJson("/api/v1/drone/commands/{$target->id}/verify", [
            'serialNumber' => 'SN-VERIFY-1',
        ])
        ->assertOk()
        ->assertJsonPath('authorized', true)
        ->assertJsonPath('command.id', $target->id)
        ->assertJsonPath('command.package.name', $package->name);

    $this->withToken($plainTextKey)
        ->postJson("/api/v1/drone/commands/{$target->id}/verify", [
            'serialNumber' => 'SN-OTHER',
        ])
        ->assertNotFound()
        ->assertJsonPath('authorized', false);

    $push->update(['status' => \App\Enums\RemotePushStatus::Cancelled]);

    $this->withToken($plainTextKey)
        ->postJson("/api/v1/drone/commands/{$target->id}/verify", [
            'serialNumber' => 'SN-VERIFY-1',
        ])
        ->assertForbidden()
        ->assertJsonPath('authorized', false);
});

test('drone command status can complete a probe target', function () {
    $organization = Organization::factory()->create();
    [, $plainTextKey] = OrganizationApiKey::issue($organization, 'Collector');
    $hardware = Hardware::factory()->create(['organization_id' => $organization->id]);
    $push = RemotePush::factory()->create([
        'organization_id' => $organization->id,
        'type' => RemotePushType::BitlockerStatus,
    ]);
    $target = RemotePushTarget::factory()->create([
        'remote_push_id' => $push->id,
        'hardware_id' => $hardware->id,
    ]);

    $this->withToken($plainTextKey)
        ->postJson("/api/v1/drone/commands/{$target->id}/status", [
            'status' => 'succeeded',
            'result' => ['volumes' => []],
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'succeeded');

    expect($target->fresh()->status)->toBe(RemotePushTargetStatus::Succeeded)
        ->and($push->fresh()->status->value)->toBe('completed');
});
