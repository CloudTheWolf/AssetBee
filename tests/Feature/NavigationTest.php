<?php

test('primary application links use Livewire navigation', function (string $view, array $routes) {
    $contents = file_get_contents(resource_path("views/{$view}"));
    $lines = preg_split('/\R/', $contents);

    foreach ($routes as $route) {
        $hasNavigableRoute = collect($lines)->contains(
            fn (string $line): bool => str_contains($line, "route('{$route}'")
                && str_contains($line, 'wire:navigate'),
        );

        expect($hasNavigableRoute)->toBeTrue("The {$route} link in {$view} must use wire:navigate.");
    }
})->with([
    'application sidebar' => ['layouts/app/sidebar.blade.php', [
        'dashboard',
        'reports.index',
        'assets.userware.index',
        'assets.hardware.index',
        'assets.cloud-tenants.index',
        'assets.virtualware.index',
        'assets.software.index',
        'profile.edit',
    ]],
    'settings navigation' => ['pages/settings/layout.blade.php', [
        'profile.edit',
        'security.edit',
        'appearance.edit',
    ]],
    'desktop user menu' => ['components/desktop-user-menu.blade.php', [
        'profile.edit',
    ]],
]);

test('Livewire actions preserve SPA navigation', function () {
    $redirectingViews = [
        'pages/assets/cloud-tenants/show.blade.php',
        'pages/assets/hardware/show.blade.php',
        'pages/assets/software/show.blade.php',
        'pages/assets/userware/show.blade.php',
        'pages/assets/virtualware/show.blade.php',
        'pages/invitations/show.blade.php',
        'pages/organizations/create.blade.php',
        'pages/settings/delete-user-modal.blade.php',
    ];

    foreach ($redirectingViews as $view) {
        expect(file_get_contents(resource_path("views/{$view}")))
            ->toMatch('/redirect\([^;]+navigate:\s*true\)/s');
    }

    expect(file_get_contents(resource_path('views/components/passkey-verify.blade.php')))
        ->toContain('Livewire.navigate(');
});
