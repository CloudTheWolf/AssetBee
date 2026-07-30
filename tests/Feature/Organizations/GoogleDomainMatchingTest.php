<?php

use App\Actions\Organizations\EnsureUserOrganization;
use App\Actions\Organizations\SyncOrganizationGoogleDomains;
use App\Models\Organization;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('users can create an organization with multiple google workspace domains', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::organizations.create')
        ->set('name', 'Bee Industries')
        ->set('google_hosted_domains', "bee.test\nbee.org, bee.co.uk")
        ->call('create')
        ->assertRedirect(route('dashboard', absolute: false));

    $organization = Organization::query()->where('name', 'Bee Industries')->first();

    expect($organization)->not->toBeNull()
        ->and($organization->googleDomains()->pluck('domain')->sort()->values()->all())
        ->toBe(['bee.co.uk', 'bee.org', 'bee.test']);
});

test('users joining via any linked google domain attach to the same organization', function () {
    $organization = Organization::factory()
        ->withGoogleDomains(['acme.com', 'acme.co.uk'])
        ->create();

    $user = User::factory()->create([
        'email' => 'ada@acme.co.uk',
    ]);

    $joined = app(EnsureUserOrganization::class)->handle($user);

    expect($joined?->is($organization))->toBeTrue()
        ->and($user->fresh()->organizations)->toHaveCount(1)
        ->and(CurrentOrganization::id())->toBe($organization->id);
});

test('google domains cannot be claimed by two organizations', function () {
    $first = Organization::factory()->withGoogleDomains(['shared.com'])->create();
    $second = Organization::factory()->create();

    app(SyncOrganizationGoogleDomains::class)->handle($second, ['shared.com']);
})->throws(ValidationException::class);

test('syncing domains replaces the previous set for an organization', function () {
    $organization = Organization::factory()
        ->withGoogleDomains(['old.com', 'keep.com'])
        ->create();

    app(SyncOrganizationGoogleDomains::class)->handle($organization, ['keep.com', 'new.com']);

    expect($organization->googleDomains()->pluck('domain')->sort()->values()->all())
        ->toBe(['keep.com', 'new.com']);
});
