<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
    $this->skipUnlessFortifyHas(Features::emailVerification());
});

test('new users receive a welcome verification email and must verify before continuing', function () {
    Notification::fake();

    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();

    $user = User::query()->where('email', 'test@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasVerifiedEmail())->toBeFalse();

    Notification::assertSentTo($user, VerifyEmail::class);

    $this->get(route('dashboard'))
        ->assertRedirect(route('verification.notice', absolute: false));
});

test('welcome verification email uses welcome subject', function () {
    $user = User::factory()->unverified()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);

    $mail = (new VerifyEmail)->toMail($user);

    expect($mail->subject)->toBe(__('Welcome to :app — verify your email', [
        'app' => config('app.name'),
    ]));
});
