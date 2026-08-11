<?php

use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Support\Registration;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('self hosted registration remains open until the first user exists', function () {
    config(['app.cloud_hosted' => false]);

    expect(Registration::isOpen())->toBeTrue();

    $this->get(route('register'))->assertOk();
});

test('self hosted registration closes after the first user is created', function () {
    config(['app.cloud_hosted' => false]);
    User::factory()->create();

    expect(Registration::isOpen())->toBeFalse();

    $this->get(route('register'))->assertRedirect(route('login'));

    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee(__('Sign up'))
        ->assertSee(__('Need access? Ask an organization owner for an invite.'));
});

test('self hosted registration still allows invited users', function () {
    config(['app.cloud_hosted' => false]);
    User::factory()->create();

    [$owner, $organization] = actingAsOrganizationMember();
    auth()->logout();

    $invitation = OrganizationInvitation::factory()->create([
        'organization_id' => $organization->id,
        'invited_by' => $owner->id,
        'email' => 'invited@example.com',
    ]);

    Registration::rememberInvitation($invitation);

    expect(Registration::isOpen($invitation))->toBeTrue();

    $this->get(route('register'))->assertOk();
});
