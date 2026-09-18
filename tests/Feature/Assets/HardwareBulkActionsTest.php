<?php

use App\Enums\HardwareStatus;
use App\Models\Hardware;
use App\Models\Userware;
use Livewire\Livewire;

test('owners can bulk change hardware status', function () {
    [, $organization] = actingAsOrganizationMember();

    $first = Hardware::factory()->create([
        'organization_id' => $organization->id,
        'status' => HardwareStatus::Available,
    ]);
    $second = Hardware::factory()->create([
        'organization_id' => $organization->id,
        'status' => HardwareStatus::Available,
    ]);
    $untouched = Hardware::factory()->create([
        'organization_id' => $organization->id,
        'status' => HardwareStatus::Available,
    ]);

    Livewire::test('pages::assets.hardware.index')
        ->set('selected', [$first->id, $second->id])
        ->set('bulkStatus', HardwareStatus::Maintenance->value)
        ->call('bulkUpdateStatus')
        ->assertHasNoErrors();

    expect($first->fresh()->status)->toBe(HardwareStatus::Maintenance)
        ->and($second->fresh()->status)->toBe(HardwareStatus::Maintenance)
        ->and($untouched->fresh()->status)->toBe(HardwareStatus::Available);
});

test('owners can bulk assign hardware', function () {
    [, $organization] = actingAsOrganizationMember();

    $userware = Userware::factory()->create(['organization_id' => $organization->id]);
    $first = Hardware::factory()->create([
        'organization_id' => $organization->id,
        'status' => HardwareStatus::Available,
    ]);
    $second = Hardware::factory()->create([
        'organization_id' => $organization->id,
        'status' => HardwareStatus::Available,
    ]);

    Livewire::test('pages::assets.hardware.index')
        ->set('selected', [$first->id, $second->id])
        ->set('bulkAssignedUserwareId', (string) $userware->id)
        ->call('bulkAssign')
        ->assertHasNoErrors();

    expect($first->fresh()->assigned_userware_id)->toBe($userware->id)
        ->and($first->fresh()->status)->toBe(HardwareStatus::Assigned)
        ->and($second->fresh()->assigned_userware_id)->toBe($userware->id)
        ->and($second->fresh()->status)->toBe(HardwareStatus::Assigned);
});

test('bulk assign skips retired hardware', function () {
    [, $organization] = actingAsOrganizationMember();

    $userware = Userware::factory()->create(['organization_id' => $organization->id]);
    $retired = Hardware::factory()->create([
        'organization_id' => $organization->id,
        'status' => HardwareStatus::Retired,
    ]);
    $available = Hardware::factory()->create([
        'organization_id' => $organization->id,
        'status' => HardwareStatus::Available,
    ]);

    Livewire::test('pages::assets.hardware.index')
        ->set('selected', [$retired->id, $available->id])
        ->set('bulkAssignedUserwareId', (string) $userware->id)
        ->call('bulkAssign')
        ->assertHasNoErrors();

    expect($retired->fresh()->assigned_userware_id)->toBeNull()
        ->and($retired->fresh()->status)->toBe(HardwareStatus::Retired)
        ->and($available->fresh()->assigned_userware_id)->toBe($userware->id)
        ->and($available->fresh()->status)->toBe(HardwareStatus::Assigned);
});

test('owners can bulk delete hardware', function () {
    [, $organization] = actingAsOrganizationMember();

    $first = Hardware::factory()->create(['organization_id' => $organization->id]);
    $second = Hardware::factory()->create(['organization_id' => $organization->id]);
    $kept = Hardware::factory()->create(['organization_id' => $organization->id]);

    Livewire::test('pages::assets.hardware.index')
        ->set('selected', [$first->id, $second->id])
        ->call('bulkDelete')
        ->assertHasNoErrors();

    expect(Hardware::query()->where('organization_id', $organization->id)->count())->toBe(1)
        ->and(Hardware::query()->find($kept->id))->not->toBeNull()
        ->and(Hardware::withTrashed()->find($first->id)?->trashed())->toBeTrue()
        ->and(Hardware::withTrashed()->find($second->id)?->trashed())->toBeTrue();
});

test('bulk actions require a selection', function () {
    actingAsOrganizationMember();

    Livewire::test('pages::assets.hardware.index')
        ->call('bulkDelete')
        ->assertHasErrors(['selected']);
});
