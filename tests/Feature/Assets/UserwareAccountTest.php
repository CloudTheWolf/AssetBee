<?php

use App\Actions\Assets\AssignSoftwareSeat;
use App\Actions\Assets\CreateUserwareAccount;
use App\Models\Software;
use App\Models\Userware;
use App\Models\UserwareAccount;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('userware show page lists assigned software', function () {
    [, $organization] = actingAsOrganizationMember();

    $userware = Userware::factory()->create(['organization_id' => $organization->id]);
    $software = Software::factory()->seatBased(5)->create([
        'organization_id' => $organization->id,
        'name' => 'Adobe Creative Cloud',
    ]);

    app(AssignSoftwareSeat::class)->handle($software, $userware);

    Livewire::test('pages::assets.userware.show', ['userware' => $userware])
        ->assertSee('Adobe Creative Cloud')
        ->assertSee(__('Assigned software'));
});

test('owners can add a software-linked account to userware', function () {
    [, $organization] = actingAsOrganizationMember();

    $userware = Userware::factory()->create(['organization_id' => $organization->id]);
    $software = Software::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Slack',
    ]);

    Livewire::test('pages::assets.userware.show', ['userware' => $userware])
        ->set('account_type', 'software')
        ->set('account_software_id', (string) $software->id)
        ->set('account_username', 'jane.doe')
        ->call('addAccount')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('userware_accounts', [
        'userware_id' => $userware->id,
        'software_id' => $software->id,
        'username' => 'jane.doe',
        'site_name' => null,
        'site_url' => null,
    ]);
});

test('owners can add an external site account to userware', function () {
    [, $organization] = actingAsOrganizationMember();

    $userware = Userware::factory()->create(['organization_id' => $organization->id]);

    Livewire::test('pages::assets.userware.show', ['userware' => $userware])
        ->set('account_type', 'external')
        ->set('account_site_name', 'GitHub')
        ->set('account_site_url', 'https://github.com')
        ->set('account_username', 'jane')
        ->call('addAccount')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('userware_accounts', [
        'userware_id' => $userware->id,
        'software_id' => null,
        'site_name' => 'GitHub',
        'site_url' => 'https://github.com',
        'username' => 'jane',
    ]);
});

test('account requires software or both site name and url', function () {
    [, $organization] = actingAsOrganizationMember();

    $userware = Userware::factory()->create(['organization_id' => $organization->id]);

    app(CreateUserwareAccount::class)->handle($userware, [
        'site_name' => 'Incomplete',
    ]);
})->throws(ValidationException::class);

test('account cannot combine software and external site', function () {
    [, $organization] = actingAsOrganizationMember();

    $userware = Userware::factory()->create(['organization_id' => $organization->id]);
    $software = Software::factory()->create(['organization_id' => $organization->id]);

    app(CreateUserwareAccount::class)->handle($userware, [
        'software_id' => $software->id,
        'site_name' => 'GitHub',
        'site_url' => 'https://github.com',
    ]);
})->throws(ValidationException::class);

test('owners can remove a userware account', function () {
    [, $organization] = actingAsOrganizationMember();

    $userware = Userware::factory()->create(['organization_id' => $organization->id]);
    $account = UserwareAccount::factory()->forExternalSite()->create([
        'organization_id' => $organization->id,
        'userware_id' => $userware->id,
    ]);

    Livewire::test('pages::assets.userware.show', ['userware' => $userware])
        ->call('deleteAccount', $account->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('userware_accounts', ['id' => $account->id]);
});
