<?php

use App\Actions\Assets\ClearCloudTenantCredentials;
use App\Actions\Assets\UpdateCloudTenantCredentials;
use App\Enums\CloudTenantProvider;
use App\Models\CloudTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('owners can save aws credentials on a cloud tenant', function () {
    [, $organization] = actingAsOrganizationMember();

    $tenant = CloudTenant::factory()->aws()->create([
        'organization_id' => $organization->id,
    ]);

    Livewire::test('pages::assets.cloud-tenants.show', ['cloudTenant' => $tenant])
        ->set('access_key_id', 'AKIAEXAMPLEKEY1234')
        ->set('secret_access_key', 'super-secret-key')
        ->set('region', 'eu-west-2')
        ->call('saveCredentials')
        ->assertHasNoErrors();

    $tenant->refresh();

    expect($tenant->hasCredentials())->toBeTrue()
        ->and($tenant->credentials['access_key_id'])->toBe('AKIAEXAMPLEKEY1234')
        ->and($tenant->credentials['secret_access_key'])->toBe('super-secret-key')
        ->and($tenant->credentials['region'])->toBe('eu-west-2');
});

test('updating credentials can keep the existing secret', function () {
    [, $organization] = actingAsOrganizationMember();

    $tenant = CloudTenant::factory()->aws()->withCredentials([
        'access_key_id' => 'AKIAOLDKEY',
        'secret_access_key' => 'original-secret',
        'region' => 'us-east-1',
    ])->create([
        'organization_id' => $organization->id,
    ]);

    app(UpdateCloudTenantCredentials::class)->handle($tenant, [
        'access_key_id' => 'AKIANEWKEY',
        'secret_access_key' => null,
        'region' => 'eu-west-1',
    ]);

    expect($tenant->fresh()->credentials)->toMatchArray([
        'access_key_id' => 'AKIANEWKEY',
        'secret_access_key' => 'original-secret',
        'region' => 'eu-west-1',
    ]);
});

test('first-time aws credentials require a secret access key', function () {
    [, $organization] = actingAsOrganizationMember();

    $tenant = CloudTenant::factory()->aws()->create([
        'organization_id' => $organization->id,
    ]);

    app(UpdateCloudTenantCredentials::class)->handle($tenant, [
        'access_key_id' => 'AKIAEXAMPLEKEY1234',
        'secret_access_key' => null,
        'region' => 'eu-west-1',
    ]);
})->throws(ValidationException::class);

test('credentials are rejected for unsupported providers', function () {
    [, $organization] = actingAsOrganizationMember();

    $tenant = CloudTenant::factory()->create([
        'organization_id' => $organization->id,
        'provider' => CloudTenantProvider::Microsoft365,
    ]);

    app(UpdateCloudTenantCredentials::class)->handle($tenant, [
        'access_key_id' => 'AKIAEXAMPLEKEY1234',
        'secret_access_key' => 'secret',
        'region' => 'eu-west-1',
    ]);
})->throws(ValidationException::class);

test('owners can clear cloud tenant credentials', function () {
    [, $organization] = actingAsOrganizationMember();

    $tenant = CloudTenant::factory()->aws()->withCredentials()->create([
        'organization_id' => $organization->id,
    ]);

    app(ClearCloudTenantCredentials::class)->handle($tenant);

    expect($tenant->fresh()->hasCredentials())->toBeFalse()
        ->and($tenant->fresh()->credentials)->toBeNull();
});

test('credentials are encrypted at rest', function () {
    [, $organization] = actingAsOrganizationMember();

    $tenant = CloudTenant::factory()->aws()->create([
        'organization_id' => $organization->id,
    ]);

    $tenant->update([
        'credentials' => [
            'access_key_id' => 'AKIAEXAMPLEKEY1234',
            'secret_access_key' => 'super-secret-key',
            'region' => 'eu-west-1',
        ],
    ]);

    $raw = DB::table('cloud_tenants')->where('id', $tenant->id)->value('credentials');

    expect($raw)->not->toBeNull()
        ->and($raw)->not->toContain('super-secret-key')
        ->and($tenant->fresh()->credentials['secret_access_key'])->toBe('super-secret-key');
});
