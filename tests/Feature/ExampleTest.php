<?php

test('home redirects guests to the login page', function () {
    $this->get(route('home'))
        ->assertRedirect(route('login'));
});

test('home redirects authenticated users to the dashboard', function () {
    actingAsOrganizationMember();

    $this->get(route('home'))
        ->assertRedirect(route('dashboard'));
});
