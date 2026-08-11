<?php

use App\Enums\UserAccountType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Features;

test('users default to customer and account type is cast to the enum', function () {
    DB::table('users')->insert([
        'name' => 'Existing Customer',
        'email' => 'existing@example.com',
        'password' => 'password',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user = User::query()->where('email', 'existing@example.com')->firstOrFail();

    expect($user->account_type)->toBe(UserAccountType::Customer)
        ->and($user->isCustomer())->toBeTrue()
        ->and($user->isSystem())->toBeFalse();
});

test('factory has customer and system states', function () {
    $customer = User::factory()->customer()->create();
    $system = User::factory()->system()->create();

    expect($customer->isCustomer())->toBeTrue()
        ->and($system->isSystem())->toBeTrue();
});

test('registration ignores submitted account type and creates a customer', function () {
    $this->skipUnlessFortifyHas(Features::registration());

    $this->post(route('register.store'), [
        'name' => 'Customer User',
        'email' => 'customer@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'account_type' => UserAccountType::System->value,
    ])->assertSessionHasNoErrors();

    expect(User::query()->where('email', 'customer@example.com')->firstOrFail()->isCustomer())->toBeTrue();
});

test('granting system status is idempotent and refuses organization members', function () {
    $dedicated = User::factory()->create(['email' => 'system@example.com']);

    $this->artisan('system:grant', ['email' => 'SYSTEM@example.com'])
        ->assertSuccessful();
    $this->artisan('system:grant', ['email' => 'system@example.com'])
        ->assertSuccessful();

    expect($dedicated->fresh()->isSystem())->toBeTrue();

    [$member] = createOrganizationMember();

    $this->artisan('system:grant', ['email' => $member->email])
        ->assertFailed();
    expect($member->fresh()->isCustomer())->toBeTrue();
});

test('granting system status is refused in self hosted mode and revocation is idempotent', function () {
    $user = User::factory()->system()->create(['email' => 'system@example.com']);
    config(['app.cloud_hosted' => false]);

    $this->artisan('system:grant', ['email' => $user->email])
        ->assertFailed();

    $this->artisan('system:revoke', ['email' => $user->email])
        ->assertSuccessful();
    $this->artisan('system:revoke', ['email' => $user->email])
        ->assertSuccessful();

    expect($user->fresh()->isCustomer())->toBeTrue();
});

test('system identities cannot create organizations or accept customer invitations', function () {
    $system = User::factory()->system()->create();
    $organization = Organization::factory()->create();

    expect($system->can('create', Organization::class))->toBeFalse();

    $organization->users()->attach($system->id, ['role' => 'member']);

    $this->actingAs($system)
        ->get(route('organizations.manage'))
        ->assertRedirect(route('system.customers'));
});
