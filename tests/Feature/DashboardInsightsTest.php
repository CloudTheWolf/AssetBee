<?php

use App\Enums\CloudTenantProvider;
use App\Enums\CostSyncSource;
use App\Enums\SoftwareBillingInterval;
use App\Enums\SoftwareLicenseType;
use App\Models\CloudTenant;
use App\Models\CostSnapshot;
use App\Models\Hardware;
use App\Models\Software;
use App\Models\SoftwareAssignment;
use App\Models\Userware;
use App\Support\OrganizationDashboardInsights;
use Illuminate\Support\Carbon;

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
        ->and($insights['top_costs'])->toHaveCount(2)
        ->and($insights['monthly_forecast'])->toHaveCount(12)
        ->and($insights['upcoming_renewals'])->not->toBeEmpty();
});

test('dashboard insights include cloud tenant and synced licence costs', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-15 12:00:00'));

    [, $organization] = actingAsOrganizationMember();

    $software = Software::factory()->recurring('monthly', 50.00)->create([
        'organization_id' => $organization->id,
        'name' => 'Synced Licence',
        'currency' => 'GBP',
        'next_billing_at' => now()->startOfMonth()->addDays(10)->toDateString(),
    ]);

    $tenant = CloudTenant::factory()->aws()->create([
        'organization_id' => $organization->id,
        'name' => 'Prod AWS',
        'billing_amount' => 75.00,
        'billing_interval' => SoftwareBillingInterval::Monthly,
        'currency' => 'GBP',
    ]);

    $pastMonth = now()->startOfMonth()->subMonthNoOverflow();

    CostSnapshot::factory()->create([
        'organization_id' => $organization->id,
        'costable_type' => Software::class,
        'costable_id' => $software->id,
        'period_start' => $pastMonth->toDateString(),
        'period_end' => $pastMonth->copy()->endOfMonth()->toDateString(),
        'amount' => 88.00,
        'currency' => 'GBP',
        'provider' => CostSyncSource::Atlassian,
    ]);

    CostSnapshot::factory()->create([
        'organization_id' => $organization->id,
        'costable_type' => CloudTenant::class,
        'costable_id' => $tenant->id,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->toDateString(),
        'amount' => 40.00,
        'currency' => 'GBP',
        'provider' => CostSyncSource::Aws,
    ]);

    $insights = app(OrganizationDashboardInsights::class)->for($organization);

    $pastKey = $pastMonth->format('Y-m');
    $currentKey = now()->format('Y-m');
    $futureKey = now()->startOfMonth()->addMonthNoOverflow()->format('Y-m');

    $pastForecast = collect($insights['monthly_forecast'])->firstWhere('key', $pastKey);
    $currentForecast = collect($insights['monthly_forecast'])->firstWhere('key', $currentKey);
    $futureForecast = collect($insights['monthly_forecast'])->firstWhere('key', $futureKey);

    expect($insights['costs']['estimated_monthly'])->toBe(125.0)
        ->and($insights['costs']['estimated_annual'])->toBe(1500.0)
        ->and($insights['costs']['upcoming_30_days'])->toBe(125.0)
        ->and($pastForecast['mode'])->toBe('actual')
        ->and($pastForecast['actual'])->toBe(163.0)
        ->and($pastForecast['estimated'])->toBeNull()
        ->and($currentForecast['mode'])->toBe('both')
        ->and($currentForecast['actual'])->toBe(90.0)
        ->and($currentForecast['estimated'])->toBe(125.0)
        ->and($futureForecast['mode'])->toBe('estimated')
        ->and($futureForecast['estimated'])->toBe(125.0)
        ->and(collect($insights['top_costs'])->pluck('name')->all())
        ->toContain('Synced Licence', 'Prod AWS')
        ->and(collect($insights['top_costs'])->firstWhere('name', 'Prod AWS')['type'])->toBe('cloud_tenant')
        ->and(collect($insights['top_costs'])->firstWhere('name', 'Synced Licence')['type'])->toBe('software')
        ->and($tenant->provider)->toBe(CloudTenantProvider::Aws);

    Carbon::setTestNow();
});

test('previous month stays provisional until the fifth of the following month', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-04 12:00:00'));

    [, $organization] = actingAsOrganizationMember();

    Software::factory()->recurring('monthly', 50.00)->create([
        'organization_id' => $organization->id,
        'currency' => 'GBP',
        'next_billing_at' => now()->startOfMonth()->addDays(10)->toDateString(),
    ]);

    CloudTenant::factory()->aws()->create([
        'organization_id' => $organization->id,
        'billing_amount' => 75.00,
        'billing_interval' => SoftwareBillingInterval::Monthly,
        'currency' => 'GBP',
    ]);

    $insights = app(OrganizationDashboardInsights::class)->for($organization);

    $previousKey = now()->startOfMonth()->subMonthNoOverflow()->format('Y-m');
    $previous = collect($insights['monthly_forecast'])->firstWhere('key', $previousKey);

    expect($previous['mode'])->toBe('both')
        ->and($previous['estimated'])->toBe(125.0)
        ->and($previous['actual'])->not->toBeNull();

    Carbon::setTestNow(Carbon::parse('2026-09-06 12:00:00'));

    $insights = app(OrganizationDashboardInsights::class)->for($organization);
    $previous = collect($insights['monthly_forecast'])->firstWhere('key', $previousKey);

    expect($previous['mode'])->toBe('actual')
        ->and($previous['estimated'])->toBeNull();

    Carbon::setTestNow();
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
        ->assertSee(__('Est. monthly spend'))
        ->assertSee(__('Estimated spend (12 months)'))
        ->assertSee(__('Actual'))
        ->assertSee(__('Estimated'))
        ->assertSee(__('Top costs by month'))
        ->assertSee('Visible Spend')
        ->assertDontSee(__('Software seats used'));
});
