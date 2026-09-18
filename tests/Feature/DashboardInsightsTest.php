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

test('upcoming renewals list child products with their own renewal dates', function () {
    [, $organization] = actingAsOrganizationMember();

    $suite = Software::factory()->recurring('monthly', 300.00)->create([
        'organization_id' => $organization->id,
        'name' => 'Atlassian',
        'vendor' => 'Atlassian',
        'currency' => 'GBP',
        'next_billing_at' => now()->addDays(3)->toDateString(),
    ]);

    Software::factory()->recurring('monthly', 180.00)->create([
        'organization_id' => $organization->id,
        'parent_software_id' => $suite->id,
        'name' => 'Jira',
        'vendor' => 'Atlassian',
        'currency' => 'GBP',
        'next_billing_at' => now()->addDays(5)->toDateString(),
    ]);

    Software::factory()->recurring('monthly', 120.00)->create([
        'organization_id' => $organization->id,
        'parent_software_id' => $suite->id,
        'name' => 'Confluence',
        'vendor' => 'Atlassian',
        'currency' => 'GBP',
        'next_billing_at' => now()->addDays(20)->toDateString(),
    ]);

    Software::factory()->recurring('monthly', 50.00)->create([
        'organization_id' => $organization->id,
        'name' => 'Standalone App',
        'currency' => 'GBP',
        'next_billing_at' => now()->addDays(10)->toDateString(),
    ]);

    $insights = app(OrganizationDashboardInsights::class)->for($organization);
    $renewals = collect($insights['upcoming_renewals']);

    expect($renewals->pluck('name')->all())->toBe(['Jira', 'Standalone App', 'Confluence'])
        ->and($renewals->pluck('name')->all())->not->toContain('Atlassian')
        ->and($renewals->firstWhere('name', 'Jira')['formatted_amount'])->toBe('GBP 180.00')
        ->and($renewals->firstWhere('name', 'Confluence')['next_billing_at'])
        ->toBe(now()->addDays(20)->toDateString());
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
        ->and($currentForecast['estimated'])->toBe(163.0)
        ->and($futureForecast['mode'])->toBe('estimated')
        ->and($futureForecast['estimated'])->toBe(163.0)
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

    $software = Software::factory()->recurring('monthly', 50.00)->create([
        'organization_id' => $organization->id,
        'currency' => 'GBP',
        'next_billing_at' => now()->startOfMonth()->addDays(10)->toDateString(),
    ]);

    $tenant = CloudTenant::factory()->aws()->create([
        'organization_id' => $organization->id,
        'billing_amount' => 75.00,
        'billing_interval' => SoftwareBillingInterval::Monthly,
        'currency' => 'GBP',
    ]);

    $july = now()->startOfMonth()->subMonthsNoOverflow(2);

    CostSnapshot::factory()->create([
        'organization_id' => $organization->id,
        'costable_type' => Software::class,
        'costable_id' => $software->id,
        'period_start' => $july->toDateString(),
        'period_end' => $july->copy()->endOfMonth()->toDateString(),
        'amount' => 60.00,
        'currency' => 'GBP',
        'provider' => CostSyncSource::Atlassian,
    ]);

    CostSnapshot::factory()->create([
        'organization_id' => $organization->id,
        'costable_type' => CloudTenant::class,
        'costable_id' => $tenant->id,
        'period_start' => $july->toDateString(),
        'period_end' => $july->copy()->endOfMonth()->toDateString(),
        'amount' => 110.00,
        'currency' => 'GBP',
        'provider' => CostSyncSource::Aws,
    ]);

    $insights = app(OrganizationDashboardInsights::class)->for($organization);

    $previousKey = now()->startOfMonth()->subMonthNoOverflow()->format('Y-m');
    $previous = collect($insights['monthly_forecast'])->firstWhere('key', $previousKey);

    expect($previous['mode'])->toBe('both')
        ->and($previous['estimated'])->toBe(170.0)
        ->and($previous['actual'])->not->toBeNull();

    Carbon::setTestNow(Carbon::parse('2026-09-06 12:00:00'));

    $insights = app(OrganizationDashboardInsights::class)->for($organization);
    $previous = collect($insights['monthly_forecast'])->firstWhere('key', $previousKey);

    expect($previous['mode'])->toBe('actual')
        ->and($previous['estimated'])->toBeNull();

    Carbon::setTestNow();
});

test('dashboard cost rollups keep Atlassian suites intact and include cloud tenants', function () {
    [, $organization] = actingAsOrganizationMember();

    $suite = Software::factory()->recurring('monthly', 300.00)->create([
        'organization_id' => $organization->id,
        'name' => 'Atlassian',
        'vendor' => 'Atlassian',
        'currency' => 'GBP',
        'next_billing_at' => now()->addDays(5)->toDateString(),
    ]);

    Software::factory()->recurring('monthly', 180.00)->create([
        'organization_id' => $organization->id,
        'parent_software_id' => $suite->id,
        'name' => 'Jira',
        'vendor' => 'Atlassian',
        'currency' => 'GBP',
    ]);

    Software::factory()->recurring('monthly', 120.00)->create([
        'organization_id' => $organization->id,
        'parent_software_id' => $suite->id,
        'name' => 'Confluence',
        'vendor' => 'Atlassian',
        'currency' => 'GBP',
    ]);

    CloudTenant::factory()->aws()->create([
        'organization_id' => $organization->id,
        'name' => 'Prod AWS',
        'billing_amount' => 90.00,
        'billing_interval' => SoftwareBillingInterval::Monthly,
        'currency' => 'GBP',
        'status' => 'active',
    ]);

    CloudTenant::factory()->aws()->create([
        'organization_id' => $organization->id,
        'name' => 'Closed AWS',
        'billing_amount' => 500.00,
        'billing_interval' => SoftwareBillingInterval::Monthly,
        'currency' => 'GBP',
        'status' => 'closed',
    ]);

    $insights = app(OrganizationDashboardInsights::class)->for($organization);
    $topNames = collect($insights['top_costs'])->pluck('name')->all();

    expect($insights['costs']['estimated_monthly'])->toBe(390.0)
        ->and($insights['costs']['estimated_annual'])->toBe(4680.0)
        ->and($topNames)->toBe(['Atlassian', 'Prod AWS'])
        ->and(collect($insights['top_costs'])->firstWhere('name', 'Prod AWS')['type'])->toBe('cloud_tenant')
        ->and($topNames)->not->toContain('Jira', 'Confluence', 'Closed AWS')
        ->and($insights['monthly_forecast_y_axis'])->toHaveCount(3)
        ->and($insights['monthly_forecast_y_axis'][2]['label'])->toBe('GBP 0.00');
});

test('dashboard rolls nested Atlassian product costs onto the suite when the parent has no billing', function () {
    [, $organization] = actingAsOrganizationMember();

    $suite = Software::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Atlassian',
        'vendor' => 'Atlassian',
        'currency' => 'GBP',
        'is_recurring' => false,
        'billing_amount' => null,
        'billing_interval' => null,
    ]);

    Software::factory()->recurring('monthly', 180.00)->create([
        'organization_id' => $organization->id,
        'parent_software_id' => $suite->id,
        'name' => 'Jira',
        'vendor' => 'Atlassian',
        'currency' => 'GBP',
    ]);

    Software::factory()->recurring('monthly', 120.00)->create([
        'organization_id' => $organization->id,
        'parent_software_id' => $suite->id,
        'name' => 'Confluence',
        'vendor' => 'Atlassian',
        'currency' => 'GBP',
    ]);

    $pastMonth = now()->startOfMonth()->subMonthNoOverflow();

    CostSnapshot::factory()->create([
        'organization_id' => $organization->id,
        'costable_type' => Software::class,
        'costable_id' => Software::query()->where('name', 'Jira')->firstOrFail()->id,
        'period_start' => $pastMonth->toDateString(),
        'period_end' => $pastMonth->copy()->endOfMonth()->toDateString(),
        'amount' => 175.00,
        'currency' => 'GBP',
        'provider' => CostSyncSource::Atlassian,
    ]);

    CostSnapshot::factory()->create([
        'organization_id' => $organization->id,
        'costable_type' => Software::class,
        'costable_id' => Software::query()->where('name', 'Confluence')->firstOrFail()->id,
        'period_start' => $pastMonth->toDateString(),
        'period_end' => $pastMonth->copy()->endOfMonth()->toDateString(),
        'amount' => 110.00,
        'currency' => 'GBP',
        'provider' => CostSyncSource::Atlassian,
    ]);

    Carbon::setTestNow(Carbon::parse($pastMonth->copy()->addMonthNoOverflow()->day(15)->toDateTimeString()));

    $insights = app(OrganizationDashboardInsights::class)->for($organization);
    $pastForecast = collect($insights['monthly_forecast'])->firstWhere('key', $pastMonth->format('Y-m'));

    expect($insights['costs']['estimated_monthly'])->toBe(300.0)
        ->and(collect($insights['top_costs'])->pluck('name')->all())->toBe(['Atlassian'])
        ->and($pastForecast['actual'])->toBe(285.0);

    Carbon::setTestNow();
});

test('top costs always includes Atlassian sites even when another currency is primary', function () {
    [, $organization] = actingAsOrganizationMember();

    $suite = Software::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Atlassian',
        'vendor' => 'Atlassian',
        'currency' => 'USD',
        'is_recurring' => false,
        'billing_amount' => null,
        'billing_interval' => null,
    ]);

    Software::factory()->recurring('monthly', 220.00)->create([
        'organization_id' => $organization->id,
        'parent_software_id' => $suite->id,
        'name' => 'Jira',
        'vendor' => 'Atlassian',
        'currency' => 'USD',
    ]);

    Software::factory()->recurring('monthly', 80.00)->create([
        'organization_id' => $organization->id,
        'parent_software_id' => $suite->id,
        'name' => 'Confluence',
        'vendor' => 'Atlassian',
        'currency' => 'USD',
    ]);

    CloudTenant::factory()->aws()->create([
        'organization_id' => $organization->id,
        'name' => 'GBP Cloud A',
        'billing_amount' => 10.00,
        'billing_interval' => SoftwareBillingInterval::Monthly,
        'currency' => 'GBP',
        'status' => 'active',
    ]);

    CloudTenant::factory()->aws()->create([
        'organization_id' => $organization->id,
        'name' => 'GBP Cloud B',
        'billing_amount' => 10.00,
        'billing_interval' => SoftwareBillingInterval::Monthly,
        'currency' => 'GBP',
        'status' => 'active',
    ]);

    $insights = app(OrganizationDashboardInsights::class)->for($organization);
    $topNames = collect($insights['top_costs'])->pluck('name')->all();

    expect($insights['costs']['currency'])->toBe('USD')
        ->and($insights['costs']['estimated_monthly'])->toBe(300.0)
        ->and($topNames)->toContain('Atlassian')
        ->and($topNames)->toContain('GBP Cloud A')
        ->and($topNames)->toContain('GBP Cloud B')
        ->and($topNames)->not->toContain('Jira', 'Confluence')
        ->and(collect($insights['top_costs'])->firstWhere('name', 'Atlassian')['formatted'])->toBe('USD 300.00');
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

test('monthly forecast uses the latest snapshot per asset instead of summing daily sync duplicates', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-15 12:00:00'));

    [, $organization] = actingAsOrganizationMember();

    $software = Software::factory()->recurring('monthly', 250.00)->create([
        'organization_id' => $organization->id,
        'name' => 'Atlassian',
        'currency' => 'GBP',
    ]);

    $pastMonth = now()->startOfMonth()->subMonthNoOverflow();

    foreach ([1, 10, 20] as $day) {
        CostSnapshot::factory()->create([
            'organization_id' => $organization->id,
            'costable_type' => Software::class,
            'costable_id' => $software->id,
            'period_start' => $pastMonth->toDateString(),
            'period_end' => $pastMonth->copy()->day($day)->toDateString(),
            'amount' => $day === 20 ? 10_000.00 : 100_000.00,
            'currency' => 'GBP',
            'provider' => CostSyncSource::Atlassian,
            'synced_at' => $pastMonth->copy()->day($day),
        ]);
    }

    $insights = app(OrganizationDashboardInsights::class)->for($organization);
    $pastForecast = collect($insights['monthly_forecast'])->firstWhere('key', $pastMonth->format('Y-m'));
    $currentForecast = collect($insights['monthly_forecast'])->firstWhere('key', now()->format('Y-m'));

    expect($pastForecast['actual'])->toBe(10_000.0)
        ->and($pastForecast['top_actual'][0]['name'])->toBe('Atlassian')
        ->and($pastForecast['top_actual'][0]['formatted'])->toBe('GBP 10,000.00')
        ->and($currentForecast['estimated'])->toBe(10_000.0)
        ->and($currentForecast['top_estimated'][0]['name'])->toBe('Atlassian')
        ->and($currentForecast['top_estimated'][0]['amount'])->toBe(10_000.0)
        ->and($currentForecast['estimated'])->not->toBe(210_000.0);

    Carbon::setTestNow();
});

test('month hover breakdown lists the three largest costs', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-15 12:00:00'));

    [, $organization] = actingAsOrganizationMember();

    foreach ([
        'Alpha Suite' => 400.00,
        'Beta Cloud' => 300.00,
        'Gamma Licence' => 200.00,
        'Delta Seats' => 50.00,
    ] as $name => $amount) {
        Software::factory()->recurring('monthly', $amount)->create([
            'organization_id' => $organization->id,
            'name' => $name,
            'currency' => 'GBP',
        ]);
    }

    $insights = app(OrganizationDashboardInsights::class)->for($organization);
    $currentForecast = collect($insights['monthly_forecast'])->firstWhere('key', now()->format('Y-m'));

    expect($currentForecast['top_estimated'])->toHaveCount(3)
        ->and(collect($currentForecast['top_estimated'])->pluck('name')->all())
        ->toBe(['Alpha Suite', 'Beta Cloud', 'Gamma Licence'])
        ->and($currentForecast['top_actual'])->toHaveCount(3)
        ->and(collect($currentForecast['top_actual'])->pluck('name')->all())
        ->toBe(['Alpha Suite', 'Beta Cloud', 'Gamma Licence'])
        ->and($insights['monthly_forecast_trend_points'])->not->toBeEmpty()
        ->and($insights['monthly_forecast_trend_points'])->toContain(',');

    Carbon::setTestNow();
});

test('top costs by month lists at most five items', function () {
    [, $organization] = actingAsOrganizationMember();

    foreach (range(1, 7) as $index) {
        Software::factory()->recurring('monthly', 100.00 - $index)->create([
            'organization_id' => $organization->id,
            'name' => "Cost {$index}",
            'currency' => 'GBP',
        ]);
    }

    $insights = app(OrganizationDashboardInsights::class)->for($organization);

    expect($insights['top_costs'])->toHaveCount(5)
        ->and(collect($insights['top_costs'])->pluck('name')->all())
        ->toBe(['Cost 1', 'Cost 2', 'Cost 3', 'Cost 4', 'Cost 5']);
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
        ->assertSee(__('Hover a month for the three largest costs.'))
        ->assertSee(__('Actual'))
        ->assertSee(__('Estimated'))
        ->assertSee(__('Trend'))
        ->assertSee(__('Top costs by month'))
        ->assertSee('Visible Spend')
        ->assertSee('GBP 50.00')
        ->assertDontSee(__('Software seats used'));
});
