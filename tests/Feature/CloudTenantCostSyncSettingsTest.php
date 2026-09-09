<?php

use App\Actions\Assets\UpdateCloudTenantCostSyncSettings;
use App\Enums\CloudTenantCostSyncProvider;
use App\Enums\CloudTenantProvider;
use App\Enums\SoftwareCostSyncProvider;
use App\Models\CloudTenant;
use App\Models\Software;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('owners can enable native cost sync when credentials exist', function () {
    [, $organization] = actingAsOrganizationMember();

    $tenant = CloudTenant::factory()->aws()->withCredentials()->create([
        'organization_id' => $organization->id,
    ]);

    Livewire::test('pages::assets.cloud-tenants.show', ['cloudTenant' => $tenant])
        ->set('cost_sync_provider', CloudTenantCostSyncProvider::Native->value)
        ->call('saveCostSync')
        ->assertHasNoErrors();

    expect($tenant->fresh()->cost_sync_provider)->toBe(CloudTenantCostSyncProvider::Native);
});

test('native cost sync requires provider credentials', function () {
    [, $organization] = actingAsOrganizationMember();

    $tenant = CloudTenant::factory()->aws()->create([
        'organization_id' => $organization->id,
    ]);

    app(UpdateCloudTenantCostSyncSettings::class)->handle($tenant, [
        'cost_sync_provider' => CloudTenantCostSyncProvider::Native->value,
    ]);
})->throws(ValidationException::class);

test('google workspace cloud tenants can store credentials for cost sync', function () {
    [, $organization] = actingAsOrganizationMember();

    $tenant = CloudTenant::factory()->create([
        'organization_id' => $organization->id,
        'provider' => CloudTenantProvider::GoogleWorkspace,
    ]);

    Livewire::test('pages::assets.cloud-tenants.show', ['cloudTenant' => $tenant])
        ->set('customer_id', 'C01234567')
        ->set('service_account_email', 'sa@example.com')
        ->set('admin_email', 'admin@example.com')
        ->set('service_account_json', '{"type":"service_account","client_email":"sa@example.com","private_key":"x"}')
        ->call('saveCredentials')
        ->assertHasNoErrors();

    expect($tenant->fresh()->hasCredentials())->toBeTrue()
        ->and($tenant->fresh()->credentials['customer_id'])->toBe('C01234567');
});

test('enabling native cost sync turns on automatic organization cost sync', function () {
    [, $organization] = actingAsOrganizationMember();
    $organization->update(['cost_sync_enabled' => false]);

    $tenant = CloudTenant::factory()->aws()->withCredentials()->create([
        'organization_id' => $organization->id,
    ]);

    Livewire::test('pages::assets.cloud-tenants.show', ['cloudTenant' => $tenant])
        ->set('cost_sync_provider', CloudTenantCostSyncProvider::Native->value)
        ->call('saveCostSync')
        ->assertHasNoErrors();

    expect($organization->fresh()->cost_sync_enabled)->toBeTrue();
});

test('google workspace credential forms expose a setup guide modal', function () {
    [, $organization] = actingAsOrganizationMember();

    $software = Software::factory()->create([
        'organization_id' => $organization->id,
    ]);

    Livewire::test('pages::assets.software.show', ['software' => $software])
        ->set('cost_sync_provider', SoftwareCostSyncProvider::GoogleWorkspace->value)
        ->assertSee('Setup guide')
        ->assertSee('Set up Google Workspace access')
        ->assertSee('https://www.googleapis.com/auth/apps.licensing', false);

    $tenant = CloudTenant::factory()->create([
        'organization_id' => $organization->id,
        'provider' => CloudTenantProvider::GoogleWorkspace,
    ]);

    Livewire::test('pages::assets.cloud-tenants.show', ['cloudTenant' => $tenant])
        ->assertSee('Setup guide')
        ->assertSee('Set up Google Workspace access');
});
