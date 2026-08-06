<?php

use App\Actions\Assets\AssignVirtualware;
use App\Models\Hardware;
use App\Models\Organization;
use App\Models\Userware;
use App\Models\Virtualware;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('owners can create virtualware', function () {
    [, $organization] = actingAsOrganizationMember();

    Livewire::test('pages::assets.virtualware.index')
        ->set('name', 'prod-api-01')
        ->set('provider', 'aws')
        ->set('category', 'vm')
        ->set('createStatus', 'running')
        ->call('create')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('virtualwares', [
        'organization_id' => $organization->id,
        'name' => 'prod-api-01',
    ]);
});

test('virtualware index can filter by provider region vpc and status', function () {
    [, $organization] = actingAsOrganizationMember();

    Virtualware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'filter-match',
        'provider' => 'aws',
        'region' => 'eu-west-1',
        'vpc_id' => 'vpc-match',
        'status' => 'running',
    ]);

    Virtualware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'filter-other-provider',
        'provider' => 'azure',
        'region' => 'eu-west-1',
        'vpc_id' => 'vpc-match',
        'status' => 'running',
    ]);

    Virtualware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'filter-other-region',
        'provider' => 'aws',
        'region' => 'us-east-1',
        'vpc_id' => 'vpc-match',
        'status' => 'running',
    ]);

    Virtualware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'filter-other-vpc',
        'provider' => 'aws',
        'region' => 'eu-west-1',
        'vpc_id' => 'vpc-other',
        'status' => 'running',
    ]);

    Virtualware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'filter-other-status',
        'provider' => 'aws',
        'region' => 'eu-west-1',
        'vpc_id' => 'vpc-match',
        'status' => 'stopped',
    ]);

    Livewire::test('pages::assets.virtualware.index')
        ->set('providerFilter', 'aws')
        ->set('regionFilter', 'eu-west-1')
        ->set('vpcFilter', 'vpc-match')
        ->set('status', 'running')
        ->assertSee('filter-match')
        ->assertDontSee('filter-other-provider')
        ->assertDontSee('filter-other-region')
        ->assertDontSee('filter-other-vpc')
        ->assertDontSee('filter-other-status');

    $component = Livewire::test('pages::assets.virtualware.index')->instance();

    expect($component->regions->all())->toContain('eu-west-1', 'us-east-1')
        ->and($component->vpcs->all())->toContain('vpc-match', 'vpc-other');
});

test('virtualware can be assigned to userware and host hardware', function () {
    [, $organization] = actingAsOrganizationMember();

    $userware = Userware::factory()->create(['organization_id' => $organization->id]);
    $hardware = Hardware::factory()->vmHost()->create(['organization_id' => $organization->id]);
    $virtualware = Virtualware::factory()->create(['organization_id' => $organization->id]);

    $virtualware = app(AssignVirtualware::class)->handle($virtualware, $userware, $hardware, updateHost: true);

    expect($virtualware->assigned_userware_id)->toBe($userware->id)
        ->and($virtualware->host_hardware_id)->toBe($hardware->id);
});

test('cross organization virtualware host is rejected', function () {
    [, $organization] = actingAsOrganizationMember();

    $virtualware = Virtualware::factory()->create(['organization_id' => $organization->id]);
    $foreignHost = Hardware::factory()->vmHost()->create([
        'organization_id' => Organization::factory()->create()->id,
    ]);

    app(AssignVirtualware::class)->handle($virtualware, null, $foreignHost, updateHost: true);
})->throws(ValidationException::class);
