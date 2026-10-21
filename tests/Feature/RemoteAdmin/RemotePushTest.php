<?php

use App\Enums\OrganizationRole;
use App\Enums\RemotePushStatus;
use App\Enums\RemotePushTargetStatus;
use App\Enums\RemotePushType;
use App\Models\DeviceGroup;
use App\Models\Hardware;
use App\Models\RemotePackage;
use App\Models\RemotePush;
use App\Models\RemotePushTarget;
use Livewire\Livewire;

test('creating a push expands group membership into targets', function () {
    [$user, $organization] = actingAsOrganizationMember(OrganizationRole::Admin);
    $hardwareA = Hardware::factory()->create(['organization_id' => $organization->id, 'name' => 'PC-A']);
    $hardwareB = Hardware::factory()->create(['organization_id' => $organization->id, 'name' => 'PC-B']);
    $group = DeviceGroup::factory()->create(['organization_id' => $organization->id]);
    $group->hardwares()->attach([$hardwareA->id, $hardwareB->id]);

    Livewire::test('pages::assets.remote-pushes.create')
        ->set('type', RemotePushType::BitlockerStatus->value)
        ->set('device_group_id', (string) $group->id)
        ->call('create')
        ->assertHasNoErrors()
        ->assertRedirect();

    $push = RemotePush::query()->sole();
    expect($push->organization_id)->toBe($organization->id)
        ->and($push->created_by)->toBe($user->id)
        ->and($push->targets)->toHaveCount(2)
        ->and($push->targets->pluck('hardware_id')->sort()->values()->all())
        ->toBe([$hardwareA->id, $hardwareB->id]);
});

test('install push requires a package and tracks incomplete targets', function () {
    [, $organization] = actingAsOrganizationMember(OrganizationRole::Owner);
    $hardware = Hardware::factory()->create(['organization_id' => $organization->id]);
    $package = RemotePackage::factory()->create(['organization_id' => $organization->id]);

    Livewire::test('pages::assets.remote-pushes.create')
        ->set('type', RemotePushType::InstallPackage->value)
        ->set('remote_package_ids', [$package->id])
        ->set('hardware_ids', [$hardware->id])
        ->call('create')
        ->assertHasNoErrors();

    $push = RemotePush::query()->sole();

    expect($push->packages)->toHaveCount(1)
        ->and($push->remote_package_id)->toBe($package->id);

    Livewire::test('pages::assets.remote-pushes.show', ['remotePush' => $push])
        ->assertSet('incompleteOnly', true)
        ->assertSee($hardware->name);

    $push->targets()->first()->update([
        'status' => RemotePushTargetStatus::Succeeded,
        'completed_at' => now(),
    ]);
    $push->refreshCompletionStatus();

    expect($push->fresh()->status)->toBe(RemotePushStatus::Completed);

    Livewire::test('pages::assets.remote-pushes.show', ['remotePush' => $push])
        ->assertSee(__('All targets have finished.'));
});

test('install push can attach multiple packages in selection order', function () {
    [, $organization] = actingAsOrganizationMember(OrganizationRole::Admin);
    $hardware = Hardware::factory()->create(['organization_id' => $organization->id]);
    $first = RemotePackage::factory()->create(['organization_id' => $organization->id, 'name' => 'Alpha App']);
    $second = RemotePackage::factory()->create(['organization_id' => $organization->id, 'name' => 'Beta App']);

    Livewire::test('pages::assets.remote-pushes.create')
        ->set('type', RemotePushType::InstallPackage->value)
        ->set('remote_package_ids', [$second->id, $first->id])
        ->set('hardware_ids', [$hardware->id])
        ->call('create')
        ->assertHasNoErrors();

    $push = RemotePush::query()->sole()->load('packages');

    expect($push->packages->pluck('id')->all())->toBe([$second->id, $first->id])
        ->and($push->remote_package_id)->toBe($second->id)
        ->and($push->packageSummary())->toContain('Beta App');

    Livewire::test('pages::assets.remote-pushes.show', ['remotePush' => $push])
        ->assertSee('Beta App')
        ->assertSee('Alpha App')
        ->assertSee(__('Install order'));
});

test('owners can delete a remote push from the index', function () {
    [, $organization] = actingAsOrganizationMember(OrganizationRole::Owner);
    $push = RemotePush::factory()->create(['organization_id' => $organization->id]);
    $hardware = Hardware::factory()->create(['organization_id' => $organization->id]);
    $target = $push->targets()->create([
        'hardware_id' => $hardware->id,
        'status' => RemotePushTargetStatus::Pending,
    ]);

    Livewire::test('pages::assets.remote-pushes.index')
        ->call('delete', $push->id)
        ->assertHasNoErrors();

    expect(RemotePush::query()->find($push->id))->toBeNull()
        ->and(RemotePushTarget::query()->find($target->id))->toBeNull();
});

test('admins can reset a finished target so the device is re-queued', function () {
    [, $organization] = actingAsOrganizationMember(OrganizationRole::Admin);
    $hardware = Hardware::factory()->create(['organization_id' => $organization->id]);
    $push = RemotePush::factory()->create([
        'organization_id' => $organization->id,
        'status' => RemotePushStatus::Completed,
        'type' => RemotePushType::BitlockerStatus,
    ]);
    $target = $push->targets()->create([
        'hardware_id' => $hardware->id,
        'status' => RemotePushTargetStatus::Succeeded,
        'first_notified_at' => now()->subHour(),
        'result_json' => ['volumes' => []],
        'completed_at' => now(),
    ]);

    Livewire::test('pages::assets.remote-pushes.show', ['remotePush' => $push])
        ->set('incompleteOnly', false)
        ->call('resetTarget', $target->id)
        ->assertHasNoErrors();

    $target->refresh();
    $push->refresh();

    expect($target->status)->toBe(RemotePushTargetStatus::Pending)
        ->and($target->result_json)->toBeNull()
        ->and($target->completed_at)->toBeNull()
        ->and($target->first_notified_at)->toBeNull()
        ->and($push->status)->toBe(RemotePushStatus::Open);
});
