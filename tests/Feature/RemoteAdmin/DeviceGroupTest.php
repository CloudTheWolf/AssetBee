<?php

use App\Enums\OrganizationRole;
use App\Models\DeviceGroup;
use App\Models\Hardware;
use Livewire\Livewire;

test('owners can create and manage device groups', function () {
    [, $organization] = actingAsOrganizationMember(OrganizationRole::Owner);
    $hardware = Hardware::factory()->create(['organization_id' => $organization->id]);

    Livewire::test('pages::assets.device-groups.index')
        ->set('name', 'Finance laptops')
        ->set('description', 'Finance team')
        ->call('create')
        ->assertHasNoErrors()
        ->assertRedirect();

    $group = DeviceGroup::query()->sole();
    expect($group->name)->toBe('Finance laptops')
        ->and($group->organization_id)->toBe($organization->id);

    Livewire::test('pages::assets.device-groups.show', ['deviceGroup' => $group])
        ->set('hardware_ids', [$hardware->id])
        ->call('save')
        ->assertHasNoErrors();

    expect($group->fresh()->hardwares()->pluck('hardwares.id')->all())->toBe([$hardware->id]);
});

test('members can view device groups but cannot create them', function () {
    actingAsOrganizationMember(OrganizationRole::Member);

    $this->get(route('assets.device-groups.index'))->assertOk();

    Livewire::test('pages::assets.device-groups.index')
        ->set('name', 'Blocked')
        ->call('create')
        ->assertForbidden();
});
