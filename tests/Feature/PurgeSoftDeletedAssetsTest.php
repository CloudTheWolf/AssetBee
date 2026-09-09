<?php

use App\Actions\Assets\PurgeSoftDeletedAssets;
use App\Jobs\PurgeSoftDeletedAssets as PurgeSoftDeletedAssetsJob;
use App\Models\CloudTenant;
use App\Models\Hardware;
use App\Models\Software;
use App\Models\SoftwareAssignment;
use App\Models\Userware;
use App\Models\UserwareAccount;
use App\Models\Virtualware;
use Illuminate\Support\Facades\Queue;

test('job releases allocations for soft-deleted software without waiting for retention', function () {
    $organization = createOrganizationMember()[1];

    $userware = Userware::factory()->create(['organization_id' => $organization->id]);
    $software = Software::factory()->seatBased(5)->create([
        'organization_id' => $organization->id,
    ]);

    SoftwareAssignment::factory()->create([
        'software_id' => $software->id,
        'userware_id' => $userware->id,
    ]);

    UserwareAccount::factory()->forSoftware($software)->create([
        'organization_id' => $organization->id,
        'userware_id' => $userware->id,
    ]);

    $software->delete();

    $result = app(PurgeSoftDeletedAssets::class)->handle(retentionDays: 30);

    expect($result['allocations_released'])->toBeGreaterThan(0)
        ->and(SoftwareAssignment::query()->where('software_id', $software->id)->count())->toBe(0)
        ->and(UserwareAccount::query()->where('userware_id', $userware->id)->value('software_id'))->toBeNull()
        ->and(Software::withTrashed()->find($software->id))->not->toBeNull()
        ->and($result['purged']['software'])->toBe(0);
});

test('job unassigns hardware and virtualware when userware is soft-deleted', function () {
    $organization = createOrganizationMember()[1];

    $userware = Userware::factory()->create(['organization_id' => $organization->id]);
    $hardware = Hardware::factory()->create([
        'organization_id' => $organization->id,
        'assigned_userware_id' => $userware->id,
    ]);
    $virtualware = Virtualware::factory()->create([
        'organization_id' => $organization->id,
        'assigned_userware_id' => $userware->id,
    ]);
    $software = Software::factory()->seatBased(5)->create([
        'organization_id' => $organization->id,
        'seat_manager_userware_id' => $userware->id,
    ]);

    SoftwareAssignment::factory()->create([
        'software_id' => $software->id,
        'userware_id' => $userware->id,
    ]);

    $userware->delete();

    app(PurgeSoftDeletedAssets::class)->handle(retentionDays: 30);

    expect($hardware->fresh()->assigned_userware_id)->toBeNull()
        ->and($virtualware->fresh()->assigned_userware_id)->toBeNull()
        ->and($software->fresh()->seat_manager_userware_id)->toBeNull()
        ->and(SoftwareAssignment::query()->where('userware_id', $userware->id)->count())->toBe(0)
        ->and(Userware::withTrashed()->find($userware->id))->not->toBeNull();
});

test('job detaches virtualware hosts and cloud tenants for soft-deleted parents', function () {
    $organization = createOrganizationMember()[1];

    $hardware = Hardware::factory()->create(['organization_id' => $organization->id]);
    $tenant = CloudTenant::factory()->aws()->create(['organization_id' => $organization->id]);
    $virtualware = Virtualware::factory()->create([
        'organization_id' => $organization->id,
        'host_hardware_id' => $hardware->id,
        'cloud_tenant_id' => $tenant->id,
    ]);

    $hardware->delete();
    $tenant->delete();

    app(PurgeSoftDeletedAssets::class)->handle(retentionDays: 30);

    expect($virtualware->fresh()->host_hardware_id)->toBeNull()
        ->and($virtualware->fresh()->cloud_tenant_id)->toBeNull()
        ->and(Hardware::withTrashed()->find($hardware->id))->not->toBeNull()
        ->and(CloudTenant::withTrashed()->find($tenant->id))->not->toBeNull();
});

test('job permanently removes soft-deleted assets after retention expires', function () {
    $organization = createOrganizationMember()[1];

    $software = Software::factory()->create(['organization_id' => $organization->id]);
    $userware = Userware::factory()->create(['organization_id' => $organization->id]);
    $hardware = Hardware::factory()->create(['organization_id' => $organization->id]);
    $virtualware = Virtualware::factory()->create(['organization_id' => $organization->id]);
    $tenant = CloudTenant::factory()->aws()->create(['organization_id' => $organization->id]);

    $software->delete();
    $userware->delete();
    $hardware->delete();
    $virtualware->delete();
    $tenant->delete();

    Software::withTrashed()->whereKey($software->id)->update(['deleted_at' => now()->subDays(31)]);
    Userware::withTrashed()->whereKey($userware->id)->update(['deleted_at' => now()->subDays(31)]);
    Hardware::withTrashed()->whereKey($hardware->id)->update(['deleted_at' => now()->subDays(31)]);
    Virtualware::withTrashed()->whereKey($virtualware->id)->update(['deleted_at' => now()->subDays(31)]);
    CloudTenant::withTrashed()->whereKey($tenant->id)->update(['deleted_at' => now()->subDays(31)]);

    $result = app(PurgeSoftDeletedAssets::class)->handle(retentionDays: 30);

    expect($result['purged'])->toMatchArray([
        'software' => 1,
        'userware' => 1,
        'hardware' => 1,
        'virtualware' => 1,
        'cloud_tenants' => 1,
    ])
        ->and(Software::withTrashed()->find($software->id))->toBeNull()
        ->and(Userware::withTrashed()->find($userware->id))->toBeNull()
        ->and(Hardware::withTrashed()->find($hardware->id))->toBeNull()
        ->and(Virtualware::withTrashed()->find($virtualware->id))->toBeNull()
        ->and(CloudTenant::withTrashed()->find($tenant->id))->toBeNull();
});

test('queued job dispatches the purge action', function () {
    Queue::fake();

    PurgeSoftDeletedAssetsJob::dispatch(retentionDays: 7);

    Queue::assertPushed(PurgeSoftDeletedAssetsJob::class, function (PurgeSoftDeletedAssetsJob $job): bool {
        return $job->retentionDays === 7;
    });
});
