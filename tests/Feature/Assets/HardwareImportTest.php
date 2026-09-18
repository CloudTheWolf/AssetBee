<?php

use App\Enums\HardwareCategory;
use App\Enums\HardwareOperatingSystem;
use App\Enums\HardwareStatus;
use App\Models\Hardware;
use App\Models\Userware;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

test('owners can import hardware from csv', function () {
    [, $organization] = actingAsOrganizationMember();

    $userware = Userware::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'ada@acme.test',
    ]);

    $csv = UploadedFile::fake()->createWithContent(
        'hardware.csv',
        "Device Name,Name,Email,OS,Serial Number\nUK-ADA-01,Ada Lovelace,ada@acme.test,Windows 11,SN-ADA-001\n",
    );

    Livewire::test('pages::assets.hardware.index')
        ->set('importFile', $csv)
        ->call('import')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('hardwares', [
        'organization_id' => $organization->id,
        'name' => 'UK-ADA-01',
        'serial_number' => 'SN-ADA-001',
        'operating_system' => HardwareOperatingSystem::Windows11->value,
        'category' => HardwareCategory::Laptop->value,
        'status' => HardwareStatus::Assigned->value,
        'assigned_userware_id' => $userware->id,
    ]);

    expect(Hardware::query()->where('organization_id', $organization->id)->count())->toBe(1);
});

test('import skips existing serial numbers and rows missing serial or email', function () {
    [, $organization] = actingAsOrganizationMember();

    $ada = Userware::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'ada@acme.test',
    ]);

    Userware::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'grace@acme.test',
    ]);

    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Existing Device',
        'serial_number' => 'SN-EXISTING',
        'assigned_userware_id' => $ada->id,
        'status' => HardwareStatus::Assigned,
    ]);

    $csv = UploadedFile::fake()->createWithContent(
        'hardware.csv',
        implode("\n", [
            'Device Name,Name,Email,OS,Serial Number',
            'Should Skip Existing,Ada Lovelace,ada@acme.test,Windows 11,SN-EXISTING',
            'Should Skip Blank Serial,Ada Lovelace,ada@acme.test,Windows 11,',
            'Should Skip Blank Email,Grace Hopper,,macOS,SN-NO-EMAIL',
            'Should Create,Grace Hopper,grace@acme.test,macOS,SN-NEW-001',
            '',
        ]),
    );

    Livewire::test('pages::assets.hardware.index')
        ->set('importFile', $csv)
        ->call('import')
        ->assertHasNoErrors();

    expect(Hardware::query()->where('organization_id', $organization->id)->count())->toBe(2);

    $this->assertDatabaseHas('hardwares', [
        'organization_id' => $organization->id,
        'name' => 'Should Create',
        'serial_number' => 'SN-NEW-001',
        'operating_system' => HardwareOperatingSystem::Macos->value,
        'status' => HardwareStatus::Assigned->value,
    ]);

    $this->assertDatabaseMissing('hardwares', [
        'organization_id' => $organization->id,
        'name' => 'Should Skip Existing',
    ]);
});

test('import skips rows when email does not match a userware', function () {
    [, $organization] = actingAsOrganizationMember();

    $csv = UploadedFile::fake()->createWithContent(
        'hardware.csv',
        "Device Name,Name,Email,OS,Serial Number\nOrphan Device,Unknown User,missing@acme.test,Linux,SN-ORPHAN\n",
    );

    Livewire::test('pages::assets.hardware.index')
        ->set('importFile', $csv)
        ->call('import')
        ->assertHasNoErrors();

    expect(Hardware::query()->where('organization_id', $organization->id)->count())->toBe(0);
});

test('import rejects csv without required headers', function () {
    actingAsOrganizationMember();

    $csv = UploadedFile::fake()->createWithContent(
        'hardware.csv',
        "Name,Email\nAda Lovelace,ada@acme.test\n",
    );

    Livewire::test('pages::assets.hardware.index')
        ->set('importFile', $csv)
        ->call('import')
        ->assertHasErrors(['importFile']);
});
