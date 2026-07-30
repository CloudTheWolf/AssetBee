<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

function createOrganizationMember(
    OrganizationRole $role = OrganizationRole::Owner,
    ?Organization $organization = null,
    ?User $user = null,
): array {
    $organization ??= Organization::factory()->create();
    $user ??= User::factory()->create();

    $organization->users()->attach($user->id, [
        'role' => $role->value,
    ]);

    return [$user, $organization];
}

function actingAsOrganizationMember(
    OrganizationRole $role = OrganizationRole::Owner,
    ?Organization $organization = null,
    ?User $user = null,
): array {
    [$user, $organization] = createOrganizationMember($role, $organization, $user);

    test()->actingAs($user);
    CurrentOrganization::set($organization);

    return [$user, $organization];
}
