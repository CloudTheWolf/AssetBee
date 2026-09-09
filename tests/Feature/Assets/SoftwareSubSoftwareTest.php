<?php

use App\Actions\Assets\CreateSoftware;
use App\Actions\Assets\UpdateSoftware;
use App\Enums\SoftwareLicenseType;
use App\Models\Software;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('software can nest under a parent suite', function () {
    [, $organization] = actingAsOrganizationMember();

    $parent = Software::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Atlassian',
    ]);

    $child = app(CreateSoftware::class)->handle($organization, [
        'name' => 'Jira',
        'vendor' => 'Atlassian',
        'parent_software_id' => $parent->id,
        'license_type' => SoftwareLicenseType::Subscription->value,
        'status' => 'active',
        'is_recurring' => false,
    ]);

    expect($child->parent_software_id)->toBe($parent->id)
        ->and($parent->fresh()->childSoftwares)->toHaveCount(1)
        ->and($parent->fresh()->childSoftwares->first()->name)->toBe('Jira');
});

test('sub-software cannot be used as a parent', function () {
    [, $organization] = actingAsOrganizationMember();

    $suite = Software::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Atlassian',
    ]);

    $jira = Software::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Jira',
        'parent_software_id' => $suite->id,
    ]);

    app(CreateSoftware::class)->handle($organization, [
        'name' => 'Jira Service Management',
        'parent_software_id' => $jira->id,
        'license_type' => SoftwareLicenseType::Subscription->value,
        'status' => 'active',
    ]);
})->throws(ValidationException::class);

test('software with children cannot become a child', function () {
    [, $organization] = actingAsOrganizationMember();

    $suite = Software::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Atlassian',
    ]);

    Software::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Jira',
        'parent_software_id' => $suite->id,
    ]);

    $other = Software::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Microsoft 365',
    ]);

    app(UpdateSoftware::class)->handle($suite, [
        'name' => $suite->name,
        'license_type' => $suite->license_type->value,
        'status' => $suite->status->value,
        'parent_software_id' => $other->id,
        'is_recurring' => false,
    ]);
})->throws(ValidationException::class);

test('owners can create sub-software from the index page', function () {
    [, $organization] = actingAsOrganizationMember();

    $parent = Software::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Atlassian',
    ]);

    Livewire::test('pages::assets.software.index')
        ->set('name', 'Confluence')
        ->set('vendor', 'Atlassian')
        ->set('parent_software_id', (string) $parent->id)
        ->set('license_type', SoftwareLicenseType::Subscription->value)
        ->set('createStatus', 'active')
        ->call('create')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('softwares', [
        'organization_id' => $organization->id,
        'name' => 'Confluence',
        'parent_software_id' => $parent->id,
    ]);
});

test('software show page lists child products', function () {
    [, $organization] = actingAsOrganizationMember();

    $parent = Software::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Atlassian',
    ]);

    Software::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Jira',
        'parent_software_id' => $parent->id,
    ]);

    Livewire::test('pages::assets.software.show', ['software' => $parent])
        ->assertSee('Sub-software')
        ->assertSee('Jira');
});
