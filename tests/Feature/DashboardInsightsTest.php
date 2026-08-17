<?php

use App\Enums\SoftwareBillingInterval;
use App\Enums\SoftwareLicenseType;
use App\Models\Hardware;
use App\Models\Software;
use App\Models\SoftwareAssignment;
use App\Models\Userware;
use App\Support\OrganizationDashboardInsights;

test('dashboard insights estimate monthly and annual software spend', function () {
    [, $organization] = actingAsOrganizationMember();

    Software::factory()->recurring('monthly', 100.00)->create([
        'organization_id' => $organization->id,
        'name' => 'Monthly App',
        'next_billing_at' => now()->addDays(5)->toDateString(),
    ]);

    Software::factory()->recurring('yearly', 1200.00)->create([
        'organization_id' => $organization->id,
        'name' => 'Yearly App',
        'next_billing_at' => now()->addMonths(2)->toDateString(),
    ]);

    $insights = app(OrganizationDashboardInsights::class)->for($organization);

    expect($insights['costs']['currency'])->toBe('GBP')
        ->and($insights['costs']['estimated_monthly'])->toBe(200.0)
        ->and($insights['costs']['estimated_annual'])->toBe(2400.0)
        ->and($insights['costs']['upcoming_30_days'])->toBe(100.0)
        ->and($insights['top_software_costs'])->toHaveCount(2)
        ->and($insights['monthly_forecast'])->toHaveCount(12)
        ->and($insights['upcoming_renewals'])->not->toBeEmpty();
});

test('dashboard insights flag underutilized seats and unassigned hardware', function () {
    [, $organization] = actingAsOrganizationMember();

    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'assigned_userware_id' => null,
        'status' => 'available',
    ]);

    $software = Software::factory()->seatBased(10)->create([
        'organization_id' => $organization->id,
        'license_type' => SoftwareLicenseType::Seat,
        'name' => 'Sparse Seats',
    ]);

    $userware = Userware::factory()->create(['organization_id' => $organization->id]);

    SoftwareAssignment::factory()->create([
        'software_id' => $software->id,
        'userware_id' => $userware->id,
    ]);

    $insights = app(OrganizationDashboardInsights::class)->for($organization);

    expect($insights['unassigned_hardware'])->toBe(1)
        ->and($insights['underutilized_seats'][0]['name'])->toBe('Sparse Seats')
        ->and($insights['underutilized_seats'][0]['unused'])->toBe(9);
});

test('dashboard page shows estimated spend labels', function () {
    [, $organization] = actingAsOrganizationMember();

    Software::factory()->recurring(SoftwareBillingInterval::Monthly->value, 42.00)->create([
        'organization_id' => $organization->id,
        'name' => 'Visible Spend',
        'next_billing_at' => now()->addDays(3)->toDateString(),
    ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('Est. monthly software spend'))
        ->assertSee(__('Estimated spend (12 months)'))
        ->assertSee(__('Top software by monthly cost'))
        ->assertSee('Visible Spend')
        ->assertDontSee(__('Software seats used'));
});
