<?php

use App\Actions\Subscriptions\CreateOrganizationBillingPortal;
use App\Actions\Subscriptions\CreateOrganizationCheckout;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Schema;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Checkout;
use Stripe\Checkout\Session;

test('cashier stores customers and subscriptions against organizations', function () {
    expect(Cashier::$customerModel)->toBe(Organization::class)
        ->and(Schema::hasColumns('organizations', ['stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at']))->toBeTrue()
        ->and(Schema::hasColumn('subscriptions', 'organization_id'))->toBeTrue()
        ->and(Schema::hasColumn('subscriptions', 'user_id'))->toBeFalse();
});

test('organization owners can view active packages available for signup', function () {
    actingAsOrganizationMember();
    SubscriptionPackage::factory()->create([
        'name' => 'Growth',
        'price' => 149,
    ]);
    SubscriptionPackage::factory()->create(['name' => 'Retired', 'is_active' => false]);

    $this->get(route('organizations.billing'))
        ->assertOk()
        ->assertSee('Growth')
        ->assertSee('Choose Growth')
        ->assertDontSee('Retired');
});

test('only customer organization owners can manage billing in cloud mode', function (OrganizationRole $role) {
    actingAsOrganizationMember($role);

    $this->get(route('organizations.billing'))->assertForbidden();
})->with([
    'admin' => OrganizationRole::Admin,
    'member' => OrganizationRole::Member,
]);

test('system users cannot use customer billing even in an explicit customer context', function () {
    $organization = Organization::factory()->create();
    $system = User::factory()->system()->create();
    $this->actingAs($system);
    CurrentOrganization::set($organization, $system);

    $this->get(route('organizations.billing'))->assertForbidden();
});

test('organization billing is disabled in self hosted mode', function () {
    config(['app.cloud_hosted' => false]);
    actingAsOrganizationMember();

    $this->get(route('organizations.billing'))->assertForbidden();
});

test('owners are redirected to Stripe Checkout for their selected package', function () {
    [, $organization] = actingAsOrganizationMember();
    $package = SubscriptionPackage::factory()->create([
        'stripe_price_id' => 'price_growth123',
    ]);

    $checkout = new Checkout($organization, Session::constructFrom([
        'url' => 'https://checkout.stripe.test/session',
    ]));

    $action = Mockery::mock(CreateOrganizationCheckout::class);
    $action->shouldReceive('handle')
        ->once()
        ->withArgs(fn (Organization $billable, SubscriptionPackage $selectedPackage) => $billable->is($organization) && $selectedPackage->is($package))
        ->andReturn($checkout);
    $this->app->instance(CreateOrganizationCheckout::class, $action);

    $this->post(route('organizations.billing.checkout', $package))
        ->assertRedirect('https://checkout.stripe.test/session')
        ->assertStatus(303);
});

test('checkout refuses packages that are not active', function () {
    actingAsOrganizationMember();
    $package = SubscriptionPackage::factory()->create(['is_active' => false]);

    $this->from(route('organizations.billing'))
        ->post(route('organizations.billing.checkout', $package))
        ->assertRedirect(route('organizations.billing'))
        ->assertSessionHasErrors('subscription');
});

test('owners can open the Stripe billing portal for an existing Stripe customer', function () {
    [, $organization] = actingAsOrganizationMember();

    $action = Mockery::mock(CreateOrganizationBillingPortal::class);
    $action->shouldReceive('handle')
        ->once()
        ->withArgs(fn (Organization $billable) => $billable->is($organization))
        ->andReturn('https://billing.stripe.test/portal');
    $this->app->instance(CreateOrganizationBillingPortal::class, $action);

    $this->post(route('organizations.billing.portal'))
        ->assertRedirect('https://billing.stripe.test/portal')
        ->assertStatus(303);
});

test('stripe webhooks are registered outside csrf protection', function () {
    $response = $this->post('/stripe/webhook');

    expect($response->getStatusCode())->not->toBe(419);
});
