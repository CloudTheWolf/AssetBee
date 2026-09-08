<?php

use App\Actions\Assets\AddSoftwareKeys;
use App\Actions\Assets\AssignSoftwareKey;
use App\Actions\Assets\AssignSoftwareSeat;
use App\Actions\Assets\BulkAssignSoftwareSeats;
use App\Actions\Assets\DeleteSoftwareKey;
use App\Actions\Assets\UnassignSoftwareSeat;
use App\Enums\SoftwareLicenseType;
use App\Models\Organization;
use App\Models\Software;
use App\Models\SoftwareAssignment;
use App\Models\SoftwareKey;
use App\Models\Userware;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('owners can create key-based software', function () {
    [, $organization] = actingAsOrganizationMember();

    Livewire::test('pages::assets.software.index')
        ->set('name', 'Adobe Photoshop')
        ->set('vendor', 'Adobe')
        ->set('license_type', 'key')
        ->set('createStatus', 'active')
        ->call('create')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('softwares', [
        'organization_id' => $organization->id,
        'name' => 'Adobe Photoshop',
        'license_type' => SoftwareLicenseType::Key->value,
        'total_seats' => null,
    ]);
});

test('owners can add multiple license keys including multiline', function () {
    [, $organization] = actingAsOrganizationMember();

    $software = Software::factory()->keyBased()->create([
        'organization_id' => $organization->id,
    ]);

    Livewire::test('pages::assets.software.show', ['software' => $software])
        ->set('newKeys', "AAAA-BBBB-CCCC-DDDD\nEEEE-FFFF-GGGG-HHHH\n")
        ->set('newKeyLabel', 'Retail')
        ->call('addKeys')
        ->assertHasNoErrors();

    expect(SoftwareKey::query()->where('software_id', $software->id)->count())->toBe(2)
        ->and(SoftwareKey::query()->where('software_id', $software->id)->first()?->label)->toBe('Retail');
});

test('a specific key can be assigned to a userware', function () {
    [, $organization] = actingAsOrganizationMember();

    $software = Software::factory()->keyBased()->create([
        'organization_id' => $organization->id,
    ]);
    $key = SoftwareKey::factory()->create(['software_id' => $software->id]);
    $userware = Userware::factory()->create(['organization_id' => $organization->id]);

    Livewire::test('pages::assets.software.show', ['software' => $software])
        ->set('assignKeyId', (string) $key->id)
        ->set('assignUserwareId', (string) $userware->id)
        ->call('assignKey')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('software_assignments', [
        'software_id' => $software->id,
        'userware_id' => $userware->id,
        'software_key_id' => $key->id,
    ]);
});

test('the same key cannot be assigned twice', function () {
    [, $organization] = actingAsOrganizationMember();

    $software = Software::factory()->keyBased()->create([
        'organization_id' => $organization->id,
    ]);
    $key = SoftwareKey::factory()->create(['software_id' => $software->id]);
    $first = Userware::factory()->create(['organization_id' => $organization->id]);
    $second = Userware::factory()->create(['organization_id' => $organization->id]);

    app(AssignSoftwareKey::class)->handle($software, $key, $first);

    expect(fn () => app(AssignSoftwareKey::class)->handle($software, $key->fresh(), $second))
        ->toThrow(ValidationException::class);
});

test('a userware cannot receive two keys for the same software', function () {
    [, $organization] = actingAsOrganizationMember();

    $software = Software::factory()->keyBased()->create([
        'organization_id' => $organization->id,
    ]);
    $firstKey = SoftwareKey::factory()->create(['software_id' => $software->id]);
    $secondKey = SoftwareKey::factory()->create(['software_id' => $software->id]);
    $userware = Userware::factory()->create(['organization_id' => $organization->id]);

    app(AssignSoftwareKey::class)->handle($software, $firstKey, $userware);

    expect(fn () => app(AssignSoftwareKey::class)->handle($software, $secondKey, $userware))
        ->toThrow(ValidationException::class);
});

test('unassigning frees the key for reassignment', function () {
    [, $organization] = actingAsOrganizationMember();

    $software = Software::factory()->keyBased()->create([
        'organization_id' => $organization->id,
    ]);
    $key = SoftwareKey::factory()->create(['software_id' => $software->id]);
    $first = Userware::factory()->create(['organization_id' => $organization->id]);
    $second = Userware::factory()->create(['organization_id' => $organization->id]);

    $assignment = app(AssignSoftwareKey::class)->handle($software, $key, $first);
    app(UnassignSoftwareSeat::class)->handle($assignment);

    $reassigned = app(AssignSoftwareKey::class)->handle($software, $key->fresh(), $second);

    expect($reassigned->software_key_id)->toBe($key->id)
        ->and($reassigned->userware_id)->toBe($second->id)
        ->and(SoftwareKey::query()->find($key->id))->not->toBeNull();
});

test('assigned keys cannot be deleted', function () {
    [, $organization] = actingAsOrganizationMember();

    $software = Software::factory()->keyBased()->create([
        'organization_id' => $organization->id,
    ]);
    $key = SoftwareKey::factory()->create(['software_id' => $software->id]);
    $userware = Userware::factory()->create(['organization_id' => $organization->id]);

    app(AssignSoftwareKey::class)->handle($software, $key, $userware);

    expect(fn () => app(DeleteSoftwareKey::class)->handle($key->fresh()))
        ->toThrow(ValidationException::class);
});

test('cross organization software key assignment is rejected', function () {
    [, $organization] = actingAsOrganizationMember();

    $software = Software::factory()->keyBased()->create([
        'organization_id' => $organization->id,
    ]);
    $key = SoftwareKey::factory()->create(['software_id' => $software->id]);
    $foreign = Userware::factory()->create([
        'organization_id' => Organization::factory()->create()->id,
    ]);

    expect(fn () => app(AssignSoftwareKey::class)->handle($software, $key, $foreign))
        ->toThrow(ValidationException::class);
});

test('seat bulk assignment is rejected for key licenses', function () {
    [, $organization] = actingAsOrganizationMember();

    $software = Software::factory()->keyBased()->create([
        'organization_id' => $organization->id,
    ]);
    SoftwareKey::factory()->create(['software_id' => $software->id]);
    $userware = Userware::factory()->create(['organization_id' => $organization->id]);

    expect(fn () => app(BulkAssignSoftwareSeats::class)->handle($software, [$userware->id]))
        ->toThrow(ValidationException::class);

    expect(fn () => app(AssignSoftwareSeat::class)->handle($software, $userware))
        ->toThrow(ValidationException::class);

    expect(SoftwareAssignment::query()->where('software_id', $software->id)->count())->toBe(0);
});

test('keys can only be added to key-based software', function () {
    [, $organization] = actingAsOrganizationMember();

    $software = Software::factory()->seatBased(5)->create([
        'organization_id' => $organization->id,
    ]);

    expect(fn () => app(AddSoftwareKeys::class)->handle($software, 'AAAA-BBBB'))
        ->toThrow(ValidationException::class);
});
