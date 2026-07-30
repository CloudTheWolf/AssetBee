<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use App\Models\Userware;
use App\Support\CurrentOrganization;
use Livewire\Livewire;

test('users without an organization are redirected to create one', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('organizations.create'));
});

test('users can create an organization', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::organizations.create')
        ->set('name', 'Bee Industries')
        ->set('google_hosted_domains', 'bee.test')
        ->call('create')
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertDatabaseHas('organizations', [
        'name' => 'Bee Industries',
    ]);

    $this->assertDatabaseHas('organization_google_domains', [
        'domain' => 'bee.test',
    ]);

    expect($user->fresh()->organizations)->toHaveCount(1);
    expect(CurrentOrganization::id())->not->toBeNull();
});

test('members cannot see other organization assets', function () {
    actingAsOrganizationMember();

    $other = Organization::factory()->create();
    Userware::factory()->create([
        'organization_id' => $other->id,
        'name' => 'Secret Identity',
        'email' => 'secret@other.test',
    ]);

    $this->get(route('assets.userware.index'))
        ->assertOk()
        ->assertDontSee('Secret Identity');
});

test('members can view but not create assets', function () {
    actingAsOrganizationMember(OrganizationRole::Member);

    $this->get(route('assets.userware.index'))
        ->assertOk()
        ->assertDontSee('data-test="create-userware"', false);
});
