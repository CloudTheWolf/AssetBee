<?php

use Laravel\Fortify\Features;
use Livewire\Livewire;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
});

test('users without two-factor or passkeys are prompted to set up security', function () {
    [$user] = createOrganizationMember();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('Secure your account'))
        ->assertSee(__('Set up security'));
});

test('users can dismiss the security setup prompt for the session', function () {
    [$user] = createOrganizationMember();

    $this->actingAs($user);

    Livewire::test('security-setup-prompt')
        ->assertSet('show', true)
        ->call('dismiss')
        ->assertSet('show', false);

    expect(session()->has('security_setup_prompt_dismissed'))->toBeTrue();

    Livewire::test('security-setup-prompt')
        ->assertSet('show', false);
});

test('users with two-factor authentication are not prompted', function () {
    [$user] = createOrganizationMember();
    $user->forceFill([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->actingAs($user);

    Livewire::test('security-setup-prompt')
        ->assertSet('show', false);
});

test('users with a passkey are not prompted', function () {
    $this->skipUnlessFortifyHas(Features::passkeys());

    [$user] = createOrganizationMember();

    $user->passkeys()->create([
        'name' => 'Laptop',
        'credential_id' => 'credential-id',
        'credential' => [
            'publicKeyCredentialId' => 'credential-id',
            'type' => 'public-key',
            'transports' => [],
            'attestationType' => 'none',
            'trustPath' => ['type' => 'empty'],
            'aaguid' => '00000000-0000-0000-0000-000000000000',
            'credentialPublicKey' => 'public-key',
            'userHandle' => (string) $user->id,
            'counter' => 0,
        ],
    ]);

    expect($user->hasConfiguredSecurity())->toBeTrue();

    $this->actingAs($user);

    Livewire::test('security-setup-prompt')
        ->assertSet('show', false);
});
