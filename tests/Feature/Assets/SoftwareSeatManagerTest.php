<?php

use App\Enums\SoftwareSeatManagerType;
use App\Models\Software;
use App\Models\Userware;
use Livewire\Livewire;

test('owners can assign a seat manager user', function () {
    [, $organization] = actingAsOrganizationMember();

    $software = Software::factory()->seatBased(5)->create([
        'organization_id' => $organization->id,
    ]);

    $manager = Userware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Seat Manager',
        'department' => 'IT',
    ]);

    Livewire::test('pages::assets.software.show', ['software' => $software])
        ->set('seat_manager_type', SoftwareSeatManagerType::Userware->value)
        ->set('seat_manager_userware_id', (string) $manager->id)
        ->call('save')
        ->assertHasNoErrors();

    $software->refresh();

    expect($software->seat_manager_type)->toBe(SoftwareSeatManagerType::Userware)
        ->and($software->seat_manager_userware_id)->toBe($manager->id)
        ->and($software->seat_manager_department)->toBeNull()
        ->and($software->seatManagerLabel())->toBe('Seat Manager');
});

test('owners can assign a seat manager department', function () {
    [, $organization] = actingAsOrganizationMember();

    $software = Software::factory()->seatBased(5)->create([
        'organization_id' => $organization->id,
    ]);

    Userware::factory()->create([
        'organization_id' => $organization->id,
        'department' => 'Engineering',
    ]);

    Livewire::test('pages::assets.software.show', ['software' => $software])
        ->set('seat_manager_type', SoftwareSeatManagerType::Department->value)
        ->set('seat_manager_department', 'Engineering')
        ->call('save')
        ->assertHasNoErrors();

    $software->refresh();

    expect($software->seat_manager_type)->toBe(SoftwareSeatManagerType::Department)
        ->and($software->seat_manager_department)->toBe('Engineering')
        ->and($software->seat_manager_userware_id)->toBeNull()
        ->and($software->seatManagerLabel())->toBe('Engineering');
});

test('clearing seat manager removes both user and department values', function () {
    [, $organization] = actingAsOrganizationMember();

    $manager = Userware::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $software = Software::factory()->seatBased(5)->create([
        'organization_id' => $organization->id,
        'seat_manager_type' => SoftwareSeatManagerType::Userware,
        'seat_manager_userware_id' => $manager->id,
    ]);

    Livewire::test('pages::assets.software.show', ['software' => $software])
        ->set('seat_manager_type', '')
        ->set('seat_manager_userware_id', '')
        ->call('save')
        ->assertHasNoErrors();

    $software->refresh();

    expect($software->seat_manager_type)->toBeNull()
        ->and($software->seat_manager_userware_id)->toBeNull()
        ->and($software->seat_manager_department)->toBeNull();
});
