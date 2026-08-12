<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Livewire;

beforeEach(function () {
    config([
        'services.google.client_id' => 'google-client-id',
        'services.google.client_secret' => 'google-client-secret',
        'services.google.redirect' => '/auth/google/callback',
        'services.google.hosted_domains' => ['acme.com'],
    ]);
});

test('authenticated users can start linking a google account', function () {
    Socialite::fake('google');

    $user = User::factory()->create([
        'email' => 'ada@acme.com',
        'google_id' => null,
    ]);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('auth.google.link'))
        ->assertRedirect();

    expect(session('google_oauth_intent'))->toBe('link');
});

test('linking google requires password confirmation', function () {
    $user = User::factory()->create([
        'email' => 'ada@acme.com',
        'google_id' => null,
    ]);

    $this->actingAs($user)
        ->get(route('auth.google.link'))
        ->assertRedirect(route('password.confirm'));
});

test('authenticated users can link a matching google account', function () {
    $user = User::factory()->create([
        'email' => 'ada@acme.com',
        'google_id' => null,
        'email_verified_at' => now(),
    ]);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-link-123',
        'name' => 'Ada Lovelace',
        'email' => 'ada@acme.com',
        'hd' => 'acme.com',
    ]));

    $this->actingAs($user)
        ->withSession([
            'auth.password_confirmed_at' => time(),
            'google_oauth_intent' => 'link',
        ])
        ->get(route('auth.google.callback'))
        ->assertRedirect(route('security.edit'))
        ->assertSessionHas('status', 'google-account-linked');

    expect($user->fresh()->google_id)->toBe('google-link-123')
        ->and(auth()->id())->toBe($user->id);
});

test('users cannot link a google account with a different email', function () {
    $user = User::factory()->create([
        'email' => 'ada@acme.com',
        'google_id' => null,
    ]);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-other',
        'name' => 'Other Person',
        'email' => 'other@acme.com',
        'hd' => 'acme.com',
    ]));

    $this->actingAs($user)
        ->withSession([
            'auth.password_confirmed_at' => time(),
            'google_oauth_intent' => 'link',
        ])
        ->get(route('auth.google.callback'))
        ->assertRedirect(route('security.edit'))
        ->assertSessionHasErrors('google');

    expect($user->fresh()->google_id)->toBeNull();
});

test('users cannot link a google account already used by someone else', function () {
    User::factory()->create([
        'email' => 'owner@acme.com',
        'google_id' => 'google-taken',
    ]);

    $user = User::factory()->create([
        'email' => 'ada@acme.com',
        'google_id' => null,
    ]);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-taken',
        'name' => 'Ada Lovelace',
        'email' => 'ada@acme.com',
        'hd' => 'acme.com',
    ]));

    $this->actingAs($user)
        ->withSession([
            'auth.password_confirmed_at' => time(),
            'google_oauth_intent' => 'link',
        ])
        ->get(route('auth.google.callback'))
        ->assertRedirect(route('security.edit'))
        ->assertSessionHasErrors('google');

    expect($user->fresh()->google_id)->toBeNull();
});

test('security settings show connect google when unlinked', function () {
    $user = User::factory()->create([
        'google_id' => null,
    ]);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertSee(__('Connected accounts'))
        ->assertSee(__('Connect Google'))
        ->assertSee(route('auth.google.link'), false);
});

test('security settings show disconnect google when linked', function () {
    $user = User::factory()->create([
        'email' => 'ada@acme.com',
        'google_id' => 'google-123',
    ]);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertSee(__('Disconnect'))
        ->assertSee(__('Connected as :email', ['email' => 'ada@acme.com']))
        ->assertDontSee(__('Connect Google'));
});

test('users can unlink their google account from security settings', function () {
    $user = User::factory()->create([
        'google_id' => 'google-123',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::settings.security')
        ->assertSet('googleLinked', true)
        ->call('unlinkGoogle')
        ->assertSet('googleLinked', false);

    expect($user->fresh()->google_id)->toBeNull();
});
