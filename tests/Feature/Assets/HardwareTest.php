<?php

use App\Actions\Assets\AssignHardware;
use App\Enums\HardwareCategory;
use App\Enums\HardwareStatus;
use App\Models\Hardware;
use App\Models\Organization;
use App\Models\Userware;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('hardware list can be filtered by type', function () {
    [, $organization] = actingAsOrganizationMember();

    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'filter-match-laptop',
        'category' => HardwareCategory::Laptop,
    ]);

    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'filter-other-server',
        'category' => HardwareCategory::Server,
    ]);

    Livewire::test('pages::assets.hardware.index')
        ->set('type', HardwareCategory::Laptop->value)
        ->assertSee('filter-match-laptop')
        ->assertDontSee('filter-other-server');
});

test('hardware list filters persist in the session between visits', function () {
    actingAsOrganizationMember();

    Livewire::test('pages::assets.hardware.index')
        ->set('search', 'MacBook')
        ->set('type', HardwareCategory::Laptop->value)
        ->set('status', HardwareStatus::Available->value);

    Livewire::test('pages::assets.hardware.index')
        ->assertSet('search', 'MacBook')
        ->assertSet('type', HardwareCategory::Laptop->value)
        ->assertSet('status', HardwareStatus::Available->value);
});

test('owners can create hardware', function () {
    [, $organization] = actingAsOrganizationMember();

    Livewire::test('pages::assets.hardware.index')
        ->set('name', 'MacBook Pro')
        ->set('asset_tag', 'HW-2001')
        ->set('category', 'laptop')
        ->set('createStatus', 'available')
        ->call('create')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('hardwares', [
        'organization_id' => $organization->id,
        'name' => 'MacBook Pro',
        'asset_tag' => 'HW-2001',
    ]);
});

test('assigning hardware to userware sets assigned status', function () {
    [, $organization] = actingAsOrganizationMember();

    $userware = Userware::factory()->create(['organization_id' => $organization->id]);
    $hardware = Hardware::factory()->create([
        'organization_id' => $organization->id,
        'status' => HardwareStatus::Available,
    ]);

    $hardware = app(AssignHardware::class)->handle($hardware, $userware);

    expect($hardware->status)->toBe(HardwareStatus::Assigned)
        ->and($hardware->assigned_userware_id)->toBe($userware->id);
});

test('unassigning hardware restores available status', function () {
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

test('cross organization hardware assignment is rejected', function () {
    [, $organization] = actingAsOrganizationMember();

    $hardware = Hardware::factory()->create(['organization_id' => $organization->id]);
    $foreignUserware = Userware::factory()->create([
        'organization_id' => Organization::factory()->create()->id,
    ]);

    app(AssignHardware::class)->handle($hardware, $foreignUserware);
})->throws(ValidationException::class);
