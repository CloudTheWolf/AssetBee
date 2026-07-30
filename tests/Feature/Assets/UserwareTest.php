<?php

use App\Enums\UserwareStatus;
use App\Models\Userware;
use Livewire\Livewire;

test('owners can create userware', function () {
    [, $organization] = actingAsOrganizationMember();

    Livewire::test('pages::assets.userware.index')
        ->set('name', 'Ada Lovelace')
        ->set('email', 'ada@acme.test')
        ->set('department', 'Engineering')
        ->set('createStatus', UserwareStatus::Active->value)
        ->call('create')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('userwares', [
        'organization_id' => $organization->id,
        'name' => 'Ada Lovelace',
        'email' => 'ada@acme.test',
    ]);
});

test('owners can update userware', function () {
    [, $organization] = actingAsOrganizationMember();

    $userware = Userware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Old Name',
        'email' => 'old@acme.test',
    ]);

    Livewire::test('pages::assets.userware.show', ['userware' => $userware])
        ->set('name', 'New Name')
        ->set('email', 'new@acme.test')
        ->set('status', UserwareStatus::Inactive->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($userware->fresh()->name)->toBe('New Name');
});

test('userware from another organization is forbidden', function () {
    actingAsOrganizationMember();

    $other = Userware::factory()->create();

    $this->get(route('assets.userware.show', $other))->assertForbidden();
});
