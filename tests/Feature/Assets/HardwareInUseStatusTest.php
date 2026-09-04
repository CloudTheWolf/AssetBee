<?php

use App\Actions\Assets\AssignHardware;
use App\Enums\HardwareStatus;
use App\Enums\InventoryReport;
use App\Models\Hardware;
use App\Models\Userware;
use Livewire\Livewire;

test('hardware can be created with in use status', function () {
    [, $organization] = actingAsOrganizationMember();

    Livewire::test('pages::assets.hardware.index')
        ->set('name', 'Proxmox Node')
        ->set('asset_tag', 'HW-INUSE-1')
        ->set('category', 'server')
        ->set('createStatus', HardwareStatus::InUse->value)
        ->set('is_vm_host', true)
        ->call('create')
        ->assertHasNoErrors();

    $hardware = Hardware::query()->where('asset_tag', 'HW-INUSE-1')->first();

    expect($hardware)->not->toBeNull()
        ->and($hardware->status)->toBe(HardwareStatus::InUse)
        ->and($hardware->organization_id)->toBe($organization->id);
});

test('unassigning in use hardware preserves in use status', function () {
    [, $organization] = actingAsOrganizationMember();

    $userware = Userware::factory()->create(['organization_id' => $organization->id]);
    $hardware = Hardware::factory()->server()->create([
        'organization_id' => $organization->id,
        'status' => HardwareStatus::InUse,
        'assigned_userware_id' => $userware->id,
    ]);

    $hardware = app(AssignHardware::class)->handle($hardware, null);

    expect($hardware->status)->toBe(HardwareStatus::InUse)
        ->and($hardware->assigned_userware_id)->toBeNull();
});

test('unassigning assigned hardware still restores available status', function () {
    [, $organization] = actingAsOrganizationMember();

    $userware = Userware::factory()->create(['organization_id' => $organization->id]);
    $hardware = Hardware::factory()->create([
        'organization_id' => $organization->id,
        'status' => HardwareStatus::Assigned,
        'assigned_userware_id' => $userware->id,
    ]);

    $hardware = app(AssignHardware::class)->handle($hardware, null);

    expect($hardware->status)->toBe(HardwareStatus::Available)
        ->and($hardware->assigned_userware_id)->toBeNull();
});

test('unassigned devices report excludes in use hardware', function () {
    [, $organization] = actingAsOrganizationMember();

    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Spare Laptop',
        'status' => HardwareStatus::Available,
        'assigned_userware_id' => null,
    ]);
    Hardware::factory()->server()->create([
        'organization_id' => $organization->id,
        'name' => 'Production Host',
        'status' => HardwareStatus::InUse,
        'assigned_userware_id' => null,
    ]);

    $this->get(route('reports.show', InventoryReport::UnassignedDevices->value))
        ->assertOk()
        ->assertSee('Spare Laptop')
        ->assertDontSee('Production Host');
});
