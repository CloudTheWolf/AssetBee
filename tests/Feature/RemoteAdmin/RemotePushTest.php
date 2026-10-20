<?php

use App\Enums\OrganizationRole;
use App\Enums\RemotePushStatus;
use App\Enums\RemotePushTargetStatus;
use App\Enums\RemotePushType;
use App\Models\DeviceGroup;
use App\Models\Hardware;
use App\Models\RemotePackage;
use App\Models\RemotePush;
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
        ->set('remote_package_id', (string) $package->id)
        ->set('hardware_ids', [$hardware->id])
        ->call('create')
        ->assertHasNoErrors();

    $push = RemotePush::query()->sole();

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
