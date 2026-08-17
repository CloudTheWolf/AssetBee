<?php

use App\Actions\Assets\AssignSoftwareSeat;
use App\Actions\Assets\BulkAssignSoftwareSeats;
use App\Models\Organization;
use App\Models\Software;
use App\Models\SoftwareAssignment;
use App\Models\Userware;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('owners can create software', function () {
    [, $organization] = actingAsOrganizationMember();

    Livewire::test('pages::assets.software.index')
        ->set('name', 'JetBrains')
        ->set('vendor', 'JetBrains')
        ->set('license_type', 'seat')
        ->set('total_seats', '5')
        ->set('createStatus', 'active')
        ->call('create')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('softwares', [
        'organization_id' => $organization->id,
        'name' => 'JetBrains',
        'total_seats' => 5,
    ]);
});

test('software seats can be assigned until capacity is reached', function () {
    [, $organization] = actingAsOrganizationMember();

    $software = Software::factory()->seatBased(1)->create([
        'organization_id' => $organization->id,
    ]);

    $first = Userware::factory()->create(['organization_id' => $organization->id]);
    $second = Userware::factory()->create(['organization_id' => $organization->id]);

    app(AssignSoftwareSeat::class)->handle($software, $first);

    expect($software->fresh()->seatsUsed())->toBe(1);

    app(AssignSoftwareSeat::class)->handle($software, $second);
})->throws(ValidationException::class);

test('cross organization software seat assignment is rejected', function () {
    [, $organization] = actingAsOrganizationMember();

    $software = Software::factory()->seatBased(5)->create([
        'organization_id' => $organization->id,
    ]);

    $foreign = Userware::factory()->create([
        'organization_id' => Organization::factory()->create()->id,
    ]);

    app(AssignSoftwareSeat::class)->handle($software, $foreign);
})->throws(ValidationException::class);

test('owners can bulk assign software seats', function () {
    [, $organization] = actingAsOrganizationMember();

    $software = Software::factory()->seatBased(5)->create([
        'organization_id' => $organization->id,
    ]);

    $identities = Userware::factory()->count(3)->create([
        'organization_id' => $organization->id,
    ]);

    Livewire::test('pages::assets.software.show', ['software' => $software])
        ->set('selectedUserwareIds', $identities->take(2)->pluck('id')->all())
        ->call('assignSeats')
        ->assertHasNoErrors();

    expect(SoftwareAssignment::query()->where('software_id', $software->id)->count())->toBe(2);
});

test('owners can assign software seats to all userware', function () {
    [, $organization] = actingAsOrganizationMember();

    $software = Software::factory()->seatBased(5)->create([
        'organization_id' => $organization->id,
    ]);

    Userware::factory()->count(3)->create([
        'organization_id' => $organization->id,
    ]);

    Livewire::test('pages::assets.software.show', ['software' => $software])
        ->call('assignAllSeats')
        ->assertHasNoErrors();

    expect(SoftwareAssignment::query()->where('software_id', $software->id)->count())->toBe(3);
});

test('bulk assignment rejects requests that exceed available seats', function () {
    [, $organization] = actingAsOrganizationMember();

    $software = Software::factory()->seatBased(2)->create([
        'organization_id' => $organization->id,
    ]);

    $identities = Userware::factory()->count(3)->create([
        'organization_id' => $organization->id,
    ]);

    expect(fn () => app(BulkAssignSoftwareSeats::class)->handle(
        $software,
        $identities->pluck('id')->all(),
    ))->toThrow(ValidationException::class);

    expect(SoftwareAssignment::query()->where('software_id', $software->id)->count())->toBe(0);
});

test('bulk assignment skips identities that already have a seat', function () {
    [, $organization] = actingAsOrganizationMember();

    $software = Software::factory()->seatBased(5)->create([
        'organization_id' => $organization->id,
    ]);

    $existing = Userware::factory()->create(['organization_id' => $organization->id]);
    $fresh = Userware::factory()->create(['organization_id' => $organization->id]);

    app(AssignSoftwareSeat::class)->handle($software, $existing);

    $result = app(BulkAssignSoftwareSeats::class)->handle($software, [
        $existing->id,
        $fresh->id,
    ]);

    expect($result['assigned'])->toBe(1)
        ->and($result['skipped'])->toBe(1)
        ->and(SoftwareAssignment::query()->where('software_id', $software->id)->count())->toBe(2);
});
