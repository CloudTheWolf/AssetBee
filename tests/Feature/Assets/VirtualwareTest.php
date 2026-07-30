<?php

use App\Actions\Assets\AssignVirtualware;
use App\Models\Hardware;
use App\Models\Organization;
use App\Models\Userware;
use App\Models\Virtualware;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('owners can create virtualware', function () {
    [, $organization] = actingAsOrganizationMember();

    Livewire::test('pages::assets.virtualware.index')
        ->set('name', 'prod-api-01')
        ->set('provider', 'aws')
        ->set('category', 'vm')
        ->set('createStatus', 'running')
        ->call('create')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('virtualwares', [
        'organization_id' => $organization->id,
        'name' => 'prod-api-01',
    ]);
});

test('virtualware can be assigned to userware and host hardware', function () {
    [, $organization] = actingAsOrganizationMember();

    $userware = Userware::factory()->create(['organization_id' => $organization->id]);
    $hardware = Hardware::factory()->vmHost()->create(['organization_id' => $organization->id]);
    $virtualware = Virtualware::factory()->create(['organization_id' => $organization->id]);

    $virtualware = app(AssignVirtualware::class)->handle($virtualware, $userware, $hardware, updateHost: true);

    expect($virtualware->assigned_userware_id)->toBe($userware->id)
        ->and($virtualware->host_hardware_id)->toBe($hardware->id);
});

test('cross organization virtualware host is rejected', function () {
    [, $organization] = actingAsOrganizationMember();

    $virtualware = Virtualware::factory()->create(['organization_id' => $organization->id]);
    $foreignHost = Hardware::factory()->vmHost()->create([
        'organization_id' => Organization::factory()->create()->id,
    ]);

    app(AssignVirtualware::class)->handle($virtualware, null, $foreignHost, updateHost: true);
})->throws(ValidationException::class);
