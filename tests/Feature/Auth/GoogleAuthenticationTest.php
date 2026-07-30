<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    config([
        'services.google.client_id' => 'google-client-id',
        'services.google.client_secret' => 'google-client-secret',
        'services.google.redirect' => '/auth/google/callback',
        'services.google.hosted_domains' => ['acme.com'],
    ]);
});

test('users are redirected to google for authentication', function () {
    Socialite::fake('google');

    $this->get(route('auth.google.redirect'))
        ->assertRedirect();
});

test('login screen includes google authentication button', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee(__('Continue with Google'))
        ->assertSee(route('auth.google.redirect'), false);
});

test('register screen includes google authentication button', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertSee(__('Sign up with Google'))
        ->assertSee(route('auth.google.redirect'), false);
});

test('new users can register with a google workspace account', function () {
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-123',
        'name' => 'Ada Lovelace',
        'email' => 'ada@acme.com',
        'hd' => 'acme.com',
    ]));

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();

    $this->assertDatabaseHas('users', [
        'google_id' => 'google-123',
        'name' => 'Ada Lovelace',
        'email' => 'ada@acme.com',
    ]);

    expect(User::query()->where('email', 'ada@acme.com')->first()->email_verified_at)->not->toBeNull();
});

test('existing users can authenticate with google', function () {
    $user = User::factory()->create([
        'email' => 'ada@acme.com',
        'google_id' => 'google-123',
    ]);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-123',
        'name' => 'Ada Lovelace',
        'email' => 'ada@acme.com',
        'hd' => 'acme.com',
    ]));

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('existing email accounts are linked to google on first workspace login', function () {
    $user = User::factory()->create([
        'email' => 'ada@acme.com',
        'google_id' => null,
    ]);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-456',
        'name' => 'Ada Lovelace',
        'email' => 'ada@acme.com',
        'hd' => 'acme.com',
    ]));

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user->fresh());

    expect($user->fresh()->google_id)->toBe('google-456');
});

test('users outside the allowed workspace domain cannot authenticate', function () {
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-789',
        'name' => 'Outside User',
        'email' => 'user@gmail.com',
        'hd' => 'gmail.com',
    ]));

    $this->from(route('login'))
        ->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'user@gmail.com']);
});

test('google authentication is available when no hosted domain is configured', function () {
    config(['services.google.hosted_domains' => []]);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-999',
        'name' => 'Personal User',
        'email' => 'personal@gmail.com',
    ]));

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
        'google_id' => 'google-999',
        'email' => 'personal@gmail.com',
    ]);
});
