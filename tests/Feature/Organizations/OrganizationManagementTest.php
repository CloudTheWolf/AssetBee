<?php

use App\Enums\OrganizationRole;
use App\Models\OrganizationApiKey;
use App\Models\User;
use Livewire\Livewire;

test('owners can view the organization management page', function () {
    actingAsOrganizationMember(OrganizationRole::Owner);

    $this->get(route('organizations.manage'))
        ->assertOk()
        ->assertSee(__('Organization'))
        ->assertSee(__('Members'))
        ->assertSee(__('Invitations'));
});

test('members cannot manage the organization', function () {
    actingAsOrganizationMember(OrganizationRole::Member);

    $this->get(route('organizations.manage'))->assertForbidden();
});

test('owners can update organization settings', function () {
    [, $organization] = actingAsOrganizationMember(OrganizationRole::Owner);

    Livewire::test('pages::organizations.manage')
        ->set('name', 'Renamed Org')
        ->set('google_hosted_domains', "one.test\ntwo.test")
        ->call('save')
        ->assertHasNoErrors();

    expect($organization->fresh()->name)->toBe('Renamed Org')
        ->and($organization->googleDomains()->pluck('domain')->sort()->values()->all())
        ->toBe(['one.test', 'two.test']);
});

test('organization admins can create and revoke api keys', function () {
    [, $organization] = actingAsOrganizationMember(OrganizationRole::Admin);

    $component = Livewire::test('pages::organizations.manage')
        ->set('api_key_name', 'Office collector')
        ->call('createApiKey')
        ->assertHasNoErrors();

    $plainTextKey = $component->get('new_api_key');
    $apiKey = OrganizationApiKey::query()->sole();

    expect($plainTextKey)->toStartWith('abk_')
        ->and($apiKey->organization_id)->toBe($organization->id)
        ->and($apiKey->name)->toBe('Office collector')
        ->and($apiKey->key_hash)->toBe(hash('sha256', $plainTextKey))
        ->and($apiKey->key_hash)->not->toBe($plainTextKey);

    $component->call('revokeApiKey', $apiKey->id)
        ->assertHasNoErrors();

    expect($apiKey->fresh()->revoked_at)->not->toBeNull();
});

test('owners can change member roles and remove members', function () {
    [, $organization] = actingAsOrganizationMember(OrganizationRole::Owner);

    $member = User::factory()->create();
    $organization->users()->attach($member->id, ['role' => OrganizationRole::Member->value]);

    Livewire::test('pages::organizations.manage')
        ->call('updateMemberRole', $member->id, OrganizationRole::Admin->value)
        ->assertHasNoErrors();

    expect($organization->users()->where('users.id', $member->id)->first()->pivot->role)
        ->toBe(OrganizationRole::Admin);

    Livewire::test('pages::organizations.manage')
        ->call('removeMember', $member->id)
        ->assertHasNoErrors();

    expect($organization->users()->where('users.id', $member->id)->exists())->toBeFalse();
});
