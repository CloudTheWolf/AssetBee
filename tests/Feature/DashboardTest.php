<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users without an organization are redirected to create one', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertRedirect(route('organizations.create'));
});

test('authenticated organization members can visit the dashboard', function () {
    actingAsOrganizationMember();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('Userware'))
        ->assertSee(__('Hardware'));
});
