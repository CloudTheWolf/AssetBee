<?php

use App\Enums\SubscriptionBillingInterval;
use App\Models\Organization;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Support\CurrentOrganization;
use Laravel\Cashier\Events\WebhookHandled;
use Livewire\Livewire;

test('cloud system users can create reusable subscription packages', function () {
    $system = User::factory()->system()->create();
    $this->actingAs($system);
    CurrentOrganization::set(Organization::factory()->create(), $system);

    Livewire::test('pages::system.packages')
        ->set('name', 'Growth')
        ->set('description', 'For growing IT teams.')
        ->set('price', '199.00')
        ->set('currency', 'usd')
        ->set('billing_interval', SubscriptionBillingInterval::Yearly->value)
        ->set('stripe_price_id', 'price_growth123')
        ->set('sort_order', 20)
        ->set('member_limit', 25)
        ->set('hardware_limit', 500)
        ->call('save')
        ->assertHasNoErrors();

    $package = SubscriptionPackage::query()->firstOrFail();

    expect($package->name)->toBe('Growth')
        ->and($package->is_active)->toBeTrue()
        ->and($package->price)->toBe('199.00')
        ->and($package->currency)->toBe('USD')
        ->and($package->billing_interval)->toBe(SubscriptionBillingInterval::Yearly)
        ->and($package->stripe_price_id)->toBe('price_growth123')
        ->and($package->member_limit)->toBe(25)
        ->and($package->hardware_limit)->toBe(500);

    $this->assertDatabaseHas('system_audits', [
        'actor_id' => $system->id,
        'organization_id' => null,
        'action' => 'subscription_package.created',
        'target_id' => $package->id,
    ]);
});

test('system users can edit packages without duplicating them', function () {
    $package = SubscriptionPackage::factory()->create(['name' => 'Starter']);
    $this->actingAs(User::factory()->system()->create());

    Livewire::test('pages::system.packages')
        ->call('edit', $package->id)
        ->set('name', 'Starter Plus')
        ->set('is_active', false)
        ->call('save')
        ->assertHasNoErrors();

    expect(SubscriptionPackage::query()->count())->toBe(1)
        ->and($package->refresh()->name)->toBe('Starter Plus')
        ->and($package->is_active)->toBeFalse();
});

test('customers and self hosted system identities cannot access package management', function () {
    $customer = User::factory()->create();
    $this->actingAs($customer)
        ->get(route('system.packages'))
        ->assertForbidden();

    config(['app.cloud_hosted' => false]);
    $system = User::factory()->system()->create();

    $this->actingAs($system)
        ->get(route('system.packages'))
        ->assertForbidden();
});

test('Stripe subscription webhooks attach and remove the matching package', function () {
    $package = SubscriptionPackage::factory()->create(['stripe_price_id' => 'price_growth123']);
    $organization = Organization::factory()->create(['stripe_id' => 'cus_acme123']);

    event(new WebhookHandled([
        'type' => 'customer.subscription.created',
        'data' => [
            'object' => [
                'customer' => 'cus_acme123',
                'items' => ['data' => [['price' => ['id' => 'price_growth123']]]],
            ],
        ],
    ]));

    expect($organization->refresh()->subscription_package_id)->toBe($package->id);

    event(new WebhookHandled([
        'type' => 'customer.subscription.deleted',
        'data' => ['object' => ['customer' => 'cus_acme123']],
    ]));

    expect($organization->refresh()->subscription_package_id)->toBeNull();
});

test('Stripe subscription updates switch organizations between catalogue packages', function () {
    $oldPackage = SubscriptionPackage::factory()->create(['stripe_price_id' => 'price_old123']);
    $newPackage = SubscriptionPackage::factory()->create(['stripe_price_id' => 'price_new123']);
    $organization = Organization::factory()->create([
        'stripe_id' => 'cus_acme123',
        'subscription_package_id' => $oldPackage->id,
    ]);

    event(new WebhookHandled([
        'type' => 'customer.subscription.updated',
        'data' => [
            'object' => [
                'customer' => 'cus_acme123',
                'items' => ['data' => [['price' => ['id' => 'price_new123']]]],
            ],
        ],
    ]));

    expect($organization->refresh()->subscription_package_id)->toBe($newPackage->id);
});
