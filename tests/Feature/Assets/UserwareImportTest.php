<?php

use App\Enums\UserwareStatus;
use App\Models\Userware;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

test('owners can import userware from csv', function () {
    [, $organization] = actingAsOrganizationMember();

    $csv = UploadedFile::fake()->createWithContent(
        'userware.csv',
        "First Name,Last Name,Email Address\nAda,Lovelace,ada@acme.test\nGrace,Hopper,grace@acme.test\n",
    );

    Livewire::test('pages::assets.userware.index')
        ->set('importFile', $csv)
        ->call('import')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('userwares', [
        'organization_id' => $organization->id,
        'name' => 'Ada Lovelace',
        'email' => 'ada@acme.test',
        'employee_id' => null,
        'department' => null,
        'status' => UserwareStatus::Active->value,
    ]);

    $this->assertDatabaseHas('userwares', [
        'organization_id' => $organization->id,
        'name' => 'Grace Hopper',
        'email' => 'grace@acme.test',
        'status' => UserwareStatus::Active->value,
    ]);

    expect(Userware::query()->where('organization_id', $organization->id)->count())->toBe(2);
});

test('import skips existing emails', function () {
    [, $organization] = actingAsOrganizationMember();

    Userware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Existing Ada',
        'email' => 'ada@acme.test',
        'department' => 'Engineering',
        'employee_id' => 'E-100',
    ]);

    $csv = UploadedFile::fake()->createWithContent(
        'userware.csv',
        "First Name,Last Name,Email Address\nAda,Lovelace,ada@acme.test\nAlan,Turing,alan@acme.test\n",
    );

    Livewire::test('pages::assets.userware.index')
        ->set('importFile', $csv)
        ->call('import')
        ->assertHasNoErrors();

    expect(Userware::query()->where('organization_id', $organization->id)->count())->toBe(2);

    $existing = Userware::query()
        ->where('organization_id', $organization->id)
        ->where('email', 'ada@acme.test')
        ->first();

    expect($existing->name)->toBe('Existing Ada')
        ->and($existing->department)->toBe('Engineering')
        ->and($existing->employee_id)->toBe('E-100');

    $this->assertDatabaseHas('userwares', [
        'organization_id' => $organization->id,
        'name' => 'Alan Turing',
        'email' => 'alan@acme.test',
        'employee_id' => null,
        'department' => null,
        'status' => UserwareStatus::Active->value,
    ]);
});

test('import rejects csv without required headers', function () {
    actingAsOrganizationMember();

    $csv = UploadedFile::fake()->createWithContent(
        'userware.csv',
        "Name,Email\nAda Lovelace,ada@acme.test\n",
    );

    Livewire::test('pages::assets.userware.index')
        ->set('importFile', $csv)
        ->call('import')
        ->assertHasErrors(['importFile']);
});
