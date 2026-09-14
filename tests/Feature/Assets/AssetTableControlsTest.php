<?php

use App\Models\CloudTenant;
use App\Models\Hardware;
use App\Models\Software;
use App\Models\SoftwareKey;
use App\Models\Userware;
use App\Models\Virtualware;
use Livewire\Livewire;

test('asset tables offer a rows per page option', function () {
    actingAsOrganizationMember();

    foreach ([
        'pages::assets.hardware.index',
        'pages::assets.software.index',
        'pages::assets.userware.index',
        'pages::assets.virtualware.index',
        'pages::assets.cloud-tenants.index',
    ] as $component) {
        Livewire::test($component)
            ->assertSee('10 per page')
            ->assertSee('25 per page')
            ->assertSee('50 per page')
            ->assertSee('100 per page');
    }
});

test('asset tables can change how many rows are shown per page', function () {
    [, $organization] = actingAsOrganizationMember();

    foreach (range(1, 11) as $index) {
        Userware::factory()->create([
            'organization_id' => $organization->id,
            'name' => sprintf('page-user-%02d', $index),
        ]);
    }

    Livewire::test('pages::assets.userware.index')
        ->assertSee('page-user-01')
        ->assertSee('page-user-10')
        ->assertDontSee('page-user-11')
        ->set('perPage', 25)
        ->assertSee('page-user-11')
        ->set('perPage', 999)
        ->assertSet('perPage', 10)
        ->assertDontSee('page-user-11');
});

test('rows per page persists between visits', function () {
    actingAsOrganizationMember();

    Livewire::test('pages::assets.hardware.index')
        ->set('perPage', 50);

    Livewire::test('pages::assets.hardware.index')
        ->assertSet('perPage', 50);
});

test('hardware can be sorted by assigned identity', function () {
    [, $organization] = actingAsOrganizationMember();

    $alpha = Userware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'assignee-alpha',
    ]);
    $zulu = Userware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'assignee-zulu',
    ]);

    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'hw-zulu-box',
        'assigned_userware_id' => $alpha->id,
    ]);
    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'hw-alpha-box',
        'assigned_userware_id' => $zulu->id,
    ]);

    Livewire::test('pages::assets.hardware.index')
        ->call('sort', 'assigned_to')
        ->assertSeeInOrder(['hw-zulu-box', 'hw-alpha-box'])
        ->call('sort', 'assigned_to')
        ->assertSeeInOrder(['hw-alpha-box', 'hw-zulu-box']);
});

test('cloud tenants can be sorted by virtualware count', function () {
    [, $organization] = actingAsOrganizationMember();

    $few = CloudTenant::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'tenant-aaa',
    ]);
    $many = CloudTenant::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'tenant-zzz',
    ]);

    Virtualware::factory()->count(3)->create([
        'organization_id' => $organization->id,
        'cloud_tenant_id' => $many->id,
    ]);
    Virtualware::factory()->create([
        'organization_id' => $organization->id,
        'cloud_tenant_id' => $few->id,
    ]);

    Livewire::test('pages::assets.cloud-tenants.index')
        ->call('sort', 'virtualwares')
        ->call('sort', 'virtualwares')
        ->assertSeeInOrder(['tenant-zzz', 'tenant-aaa']);
});

test('virtualware can be sorted by placement and assignee', function () {
    [, $organization] = actingAsOrganizationMember();

    $tenant = CloudTenant::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'placement-alpha',
    ]);
    $host = Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'placement-zulu',
    ]);
    $alpha = Userware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'assignee-alpha',
    ]);
    $zulu = Userware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'assignee-zulu',
    ]);

    Virtualware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'vw-zzz',
        'cloud_tenant_id' => $tenant->id,
        'assigned_userware_id' => $zulu->id,
    ]);
    Virtualware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'vw-aaa',
        'host_hardware_id' => $host->id,
        'assigned_userware_id' => $alpha->id,
    ]);

    Livewire::test('pages::assets.virtualware.index')
        ->call('sort', 'placement')
        ->assertSeeInOrder(['vw-zzz', 'vw-aaa'])
        ->call('sort', 'assigned_to')
        ->assertSeeInOrder(['vw-aaa', 'vw-zzz']);
});

test('software can be sorted by seats and billing amount', function () {
    [, $organization] = actingAsOrganizationMember();

    Software::factory()->seatBased(50)->create([
        'organization_id' => $organization->id,
        'name' => 'license-aaa',
    ]);
    Software::factory()->recurring(amount: 10)->create([
        'organization_id' => $organization->id,
        'name' => 'license-zzz',
    ]);

    $manyKeys = Software::factory()->keyBased()->create([
        'organization_id' => $organization->id,
        'name' => 'key-aaa',
    ]);
    $fewKeys = Software::factory()->keyBased()->create([
        'organization_id' => $organization->id,
        'name' => 'key-zzz',
    ]);
    SoftwareKey::factory()->count(3)->create(['software_id' => $manyKeys->id]);
    SoftwareKey::factory()->create(['software_id' => $fewKeys->id]);

    Livewire::test('pages::assets.software.index')
        ->call('sort', 'seats')
        ->assertSeeInOrder(['key-zzz', 'license-zzz', 'key-aaa', 'license-aaa']);
});

test('unknown sort columns are ignored', function () {
    actingAsOrganizationMember();

    Livewire::test('pages::assets.hardware.index')
        ->call('sort', 'id')
        ->assertSet('sortBy', 'name')
        ->assertSet('sortDirection', 'asc');
});
