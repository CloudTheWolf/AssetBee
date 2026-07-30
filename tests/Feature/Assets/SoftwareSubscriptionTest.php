<?php

use App\Actions\Assets\CreateSoftware;
use App\Enums\SoftwareBillingInterval;
use App\Enums\SoftwareLicenseType;
use App\Models\Software;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('software can be created with recurring subscription details', function () {
    [, $organization] = actingAsOrganizationMember();

    $software = app(CreateSoftware::class)->handle($organization, [
        'name' => 'Microsoft 365',
        'vendor' => 'Microsoft',
        'license_type' => SoftwareLicenseType::Subscription->value,
        'status' => 'active',
        'is_recurring' => true,
        'billing_interval' => SoftwareBillingInterval::Monthly->value,
        'billing_amount' => 12.50,
        'currency' => 'gbp',
        'next_billing_at' => now()->addMonth()->toDateString(),
    ]);

    expect($software->is_recurring)->toBeTrue()
        ->and($software->billing_interval)->toBe(SoftwareBillingInterval::Monthly)
        ->and((float) $software->billing_amount)->toBe(12.50)
        ->and($software->currency)->toBe('GBP')
        ->and($software->next_billing_at)->not->toBeNull();
});

test('recurring software requires a billing interval', function () {
    [, $organization] = actingAsOrganizationMember();

    app(CreateSoftware::class)->handle($organization, [
        'name' => 'Adobe CC',
        'license_type' => SoftwareLicenseType::Subscription->value,
        'status' => 'active',
        'is_recurring' => true,
    ]);
})->throws(ValidationException::class);

test('owners can create recurring software from the index page', function () {
    [, $organization] = actingAsOrganizationMember();

    Livewire::test('pages::assets.software.index')
        ->set('name', 'Slack')
        ->set('license_type', 'subscription')
        ->set('createStatus', 'active')
        ->set('is_recurring', true)
        ->set('billing_interval', 'yearly')
        ->set('billing_amount', '99.00')
        ->set('currency', 'GBP')
        ->set('next_billing_at', now()->addYear()->toDateString())
        ->call('create')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('softwares', [
        'organization_id' => $organization->id,
        'name' => 'Slack',
        'is_recurring' => 1,
        'billing_interval' => 'yearly',
        'currency' => 'GBP',
    ]);
});

test('disabling recurring clears billing fields', function () {
    [, $organization] = actingAsOrganizationMember();

    $software = Software::factory()->recurring()->create([
        'organization_id' => $organization->id,
    ]);

    Livewire::test('pages::assets.software.show', ['software' => $software])
        ->set('is_recurring', false)
        ->call('save')
        ->assertHasNoErrors();

    $software->refresh();

    expect($software->is_recurring)->toBeFalse()
        ->and($software->billing_interval)->toBeNull()
        ->and($software->billing_amount)->toBeNull()
        ->and($software->next_billing_at)->toBeNull();
});
