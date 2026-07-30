<?php

use App\Actions\Assets\AssignVirtualware;
use App\Actions\Assets\CreateCloudTenant;
use App\Enums\CloudTenantProvider;
use App\Enums\CloudTenantStatus;
use App\Models\CloudTenant;
use App\Models\Organization;
use App\Models\Virtualware;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('owners can create cloud tenants', function () {
    [, $organization] = actingAsOrganizationMember();

    Livewire::test('pages::assets.cloud-tenants.index')
        ->set('name', 'Contoso M365')
        ->set('provider', CloudTenantProvider::Microsoft365->value)
        ->set('domain', 'contoso.com')
        ->set('createStatus', CloudTenantStatus::Active->value)
        ->call('create')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('cloud_tenants', [
        'organization_id' => $organization->id,
        'name' => 'Contoso M365',
        'domain' => 'contoso.com',
        'provider' => 'microsoft365',
    ]);
});

test('virtualware can be linked to a cloud tenant', function () {
    [, $organization] = actingAsOrganizationMember();

    $tenant = CloudTenant::factory()->create(['organization_id' => $organization->id]);
    $virtualware = Virtualware::factory()->create(['organization_id' => $organization->id]);

    $virtualware = app(AssignVirtualware::class)->handle(
        $virtualware,
        cloudTenant: $tenant,
        updateCloudTenant: true,
    );

    expect($virtualware->cloud_tenant_id)->toBe($tenant->id);
});

test('cross organization cloud tenant assignment is rejected', function () {
    [, $organization] = actingAsOrganizationMember();

    $virtualware = Virtualware::factory()->create(['organization_id' => $organization->id]);
    $foreignTenant = CloudTenant::factory()->create([
        'organization_id' => Organization::factory()->create()->id,
    ]);

    app(AssignVirtualware::class)->handle(
        $virtualware,
        cloudTenant: $foreignTenant,
        updateCloudTenant: true,
    );
})->throws(ValidationException::class);

test('create cloud tenant action validates required fields', function () {
    [, $organization] = actingAsOrganizationMember();

    app(CreateCloudTenant::class)->handle($organization, [
        'name' => '',
        'provider' => CloudTenantProvider::Aws->value,
        'status' => CloudTenantStatus::Active->value,
    ]);
})->throws(ValidationException::class);

test('deleting a cloud tenant unlinks virtualware', function () {
    [, $organization] = actingAsOrganizationMember();

    $tenant = CloudTenant::factory()->create(['organization_id' => $organization->id]);
    $virtualware = Virtualware::factory()->create([
        'organization_id' => $organization->id,
        'cloud_tenant_id' => $tenant->id,
    ]);

    Livewire::test('pages::assets.cloud-tenants.show', ['cloudTenant' => $tenant])
        ->call('delete');

    expect($virtualware->fresh()->cloud_tenant_id)->toBeNull()
        ->and($tenant->fresh()->deleted_at)->not->toBeNull();
});
