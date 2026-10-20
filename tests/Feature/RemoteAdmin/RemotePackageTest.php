<?php

use App\Actions\RemoteAdmin\CreateRemotePackage;
use App\Enums\OrganizationRole;
use App\Enums\RemotePackageSource;
use App\Models\Organization;
use App\Models\RemotePackage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('owners can create winget packages from the catalog ui', function () {
    [, $organization] = actingAsOrganizationMember(OrganizationRole::Owner);

    Livewire::test('pages::assets.remote-packages.index')
        ->set('name', 'GCPW')
        ->set('source', RemotePackageSource::Winget->value)
        ->set('winget_id', 'Google.GoogleCredentialProviderforWindows')
        ->call('save')
        ->assertHasNoErrors();

    $package = RemotePackage::query()->sole();
    expect($package->organization_id)->toBe($organization->id)
        ->and($package->source)->toBe(RemotePackageSource::Winget)
        ->and($package->winget_id)->toBe('Google.GoogleCredentialProviderforWindows');
});

test('msi packages require https urls', function () {
    $organization = Organization::factory()->create();
    $action = app(CreateRemotePackage::class);

    expect(fn () => $action->handle($organization, [
        'name' => 'Bad MSI',
        'source' => RemotePackageSource::Msi->value,
        'msi_url' => 'http://example.com/package.msi',
    ]))->toThrow(ValidationException::class);

    $package = $action->handle($organization, [
        'name' => 'Good MSI',
        'source' => RemotePackageSource::Msi->value,
        'msi_url' => 'https://example.com/package.msi',
        'sha256' => str_repeat('a', 64),
    ]);

    expect($package->msi_url)->toBe('https://example.com/package.msi')
        ->and($package->sha256)->toBe(str_repeat('a', 64));
});
