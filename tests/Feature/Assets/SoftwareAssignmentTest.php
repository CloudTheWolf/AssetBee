<?php

use App\Actions\Assets\AssignSoftwareSeat;
use App\Models\Organization;
use App\Models\Software;
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
