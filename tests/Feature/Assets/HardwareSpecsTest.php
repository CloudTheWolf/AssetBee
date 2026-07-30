<?php

use App\Actions\Assets\AssignVirtualware;
use App\Actions\Assets\CreateHardware;
use App\Actions\Assets\UpdateHardware;
use App\Enums\BitLockerStatus;
use App\Enums\HardwareCategory;
use App\Enums\HardwareOperatingSystem;
use App\Models\CloudTenant;
use App\Models\Hardware;
use App\Models\Virtualware;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('creating a laptop shows and stores compute specs', function () {
    [, $organization] = actingAsOrganizationMember();

    Livewire::test('pages::assets.hardware.index')
        ->set('name', 'Finance Laptop')
        ->set('category', 'laptop')
        ->set('createStatus', 'available')
        ->set('operating_system', HardwareOperatingSystem::Windows11->value)
        ->set('cpu', 'Intel i7')
        ->set('ram_gb', '16')
        ->set('storage_gb', '512')
        ->set('bitlocker_status', BitLockerStatus::Enabled->value)
        ->set('bitlocker_recovery_key', '123456-789012-345678-901234')
        ->call('create')
        ->assertHasNoErrors();

    $hardware = Hardware::query()->where('organization_id', $organization->id)->first();

    expect($hardware)->not->toBeNull()
        ->and($hardware->operating_system)->toBe(HardwareOperatingSystem::Windows11)
        ->and($hardware->ram_gb)->toBe(16)
        ->and($hardware->bitlocker_status)->toBe(BitLockerStatus::Enabled)
        ->and($hardware->bitlocker_recovery_key)->toBe('123456-789012-345678-901234');
});

test('servers can be marked as vm hosts', function () {
    [, $organization] = actingAsOrganizationMember();

    $hardware = app(CreateHardware::class)->handle($organization, [
        'name' => 'esxi-01',
        'category' => HardwareCategory::Server->value,
        'status' => 'available',
        'is_vm_host' => true,
        'operating_system' => HardwareOperatingSystem::Linux->value,
    ]);

    expect($hardware->category)->toBe(HardwareCategory::Server)
        ->and($hardware->is_vm_host)->toBeTrue();
});

test('non servers cannot be marked as vm hosts', function () {
    [, $organization] = actingAsOrganizationMember();

    app(CreateHardware::class)->handle($organization, [
        'name' => 'office-pc',
        'category' => HardwareCategory::Desktop->value,
        'status' => 'available',
        'is_vm_host' => true,
    ]);
})->throws(ValidationException::class);

test('bitlocker fields clear when os is not windows', function () {
    [, $organization] = actingAsOrganizationMember();

    $hardware = Hardware::factory()->create([
        'organization_id' => $organization->id,
        'category' => HardwareCategory::Laptop,
        'operating_system' => HardwareOperatingSystem::Windows11,
        'bitlocker_status' => BitLockerStatus::Enabled,
        'bitlocker_recovery_key' => 'secret-key',
    ]);

    $hardware = app(UpdateHardware::class)->handle($hardware, [
        'name' => $hardware->name,
        'category' => HardwareCategory::Laptop->value,
        'status' => $hardware->status->value,
        'operating_system' => HardwareOperatingSystem::Macos->value,
        'bitlocker_status' => BitLockerStatus::Enabled->value,
        'bitlocker_recovery_key' => 'should-clear',
    ]);

    expect($hardware->operating_system)->toBe(HardwareOperatingSystem::Macos)
        ->and($hardware->bitlocker_status)->toBeNull()
        ->and($hardware->bitlocker_recovery_key)->toBeNull();
});

test('virtualware can be assigned to a vm host', function () {
    [, $organization] = actingAsOrganizationMember();

    $host = Hardware::factory()->vmHost()->create(['organization_id' => $organization->id]);
    $virtualware = Virtualware::factory()->create(['organization_id' => $organization->id]);

    $virtualware = app(AssignVirtualware::class)->handle(
        $virtualware,
        host: $host,
        updateHost: true,
        updateCloudTenant: true,
    );

    expect($virtualware->host_hardware_id)->toBe($host->id)
        ->and($virtualware->cloud_tenant_id)->toBeNull();
});

test('virtualware cannot use non vm host hardware', function () {
    [, $organization] = actingAsOrganizationMember();

    $hardware = Hardware::factory()->create([
        'organization_id' => $organization->id,
        'category' => HardwareCategory::Laptop,
        'is_vm_host' => false,
    ]);
    $virtualware = Virtualware::factory()->create(['organization_id' => $organization->id]);

    app(AssignVirtualware::class)->handle(
        $virtualware,
        host: $hardware,
        updateHost: true,
    );
})->throws(ValidationException::class);

test('virtualware cannot be linked to both cloud tenant and vm host', function () {
    [, $organization] = actingAsOrganizationMember();

    $host = Hardware::factory()->vmHost()->create(['organization_id' => $organization->id]);
    $tenant = CloudTenant::factory()->create(['organization_id' => $organization->id]);
    $virtualware = Virtualware::factory()->create(['organization_id' => $organization->id]);

    app(AssignVirtualware::class)->handle(
        $virtualware,
        host: $host,
        updateHost: true,
        cloudTenant: $tenant,
        updateCloudTenant: true,
    );
})->throws(ValidationException::class);

test('selecting a cloud tenant clears an existing vm host', function () {
    [, $organization] = actingAsOrganizationMember();

    $host = Hardware::factory()->vmHost()->create(['organization_id' => $organization->id]);
    $tenant = CloudTenant::factory()->create(['organization_id' => $organization->id]);
    $virtualware = Virtualware::factory()->create([
        'organization_id' => $organization->id,
        'host_hardware_id' => $host->id,
    ]);

    $virtualware = app(AssignVirtualware::class)->handle(
        $virtualware,
        cloudTenant: $tenant,
        updateCloudTenant: true,
    );

    expect($virtualware->cloud_tenant_id)->toBe($tenant->id)
        ->and($virtualware->host_hardware_id)->toBeNull();
});
