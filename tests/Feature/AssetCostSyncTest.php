<?php

use App\Actions\Assets\SyncAssetCosts;
use App\Contracts\Costs\FetchesAssetCosts;
use App\Data\FetchedCostPeriod;
use App\Enums\CloudTenantCostSyncProvider;
use App\Enums\CloudTenantProvider;
use App\Enums\CostSyncSource;
use App\Enums\SoftwareBillingInterval;
use App\Enums\SoftwareCostSyncProvider;
use App\Enums\SoftwareLicenseType;
use App\Models\CloudTenant;
use App\Models\CostSnapshot;
use App\Models\Software;
use App\Services\Costs\CostFetchManager;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use RuntimeException;

beforeEach(function () {
    $this->fakeFetcher = new class implements FetchesAssetCosts
    {
        /** @var list<FetchedCostPeriod> */
        public array $periods = [];

        public bool $shouldFail = false;

        public function supports(Software|CloudTenant $asset): bool
        {
            return $asset->hasCostSyncConfigured();
        }

        public function fetch(Software|CloudTenant $asset, CarbonInterface $from, CarbonInterface $to): array
        {
            if ($this->shouldFail) {
                throw new RuntimeException('Provider denied access.');
            }

            return $this->periods;
        }
    };

    $this->app->instance(
        CostFetchManager::class,
        new CostFetchManager([$this->fakeFetcher]),
    );
});

test('syncing software costs writes snapshots and updates billing totals', function () {
    [, $organization] = actingAsOrganizationMember();

    $software = Software::factory()->seatBased(5)->create([
        'organization_id' => $organization->id,
        'cost_sync_provider' => SoftwareCostSyncProvider::Atlassian,
        'cost_sync_credentials' => [
            'organization_id' => 'org-1',
            'email' => 'admin@example.com',
            'api_token' => 'token',
        ],
        'currency' => 'USD',
    ]);

    $this->fakeFetcher->periods = [
        new FetchedCostPeriod(
            periodStart: CarbonImmutable::now()->startOfMonth()->subMonth(),
            periodEnd: CarbonImmutable::now()->startOfMonth()->subDay(),
            amount: 120.50,
            currency: 'USD',
            provider: CostSyncSource::Atlassian,
            seatCount: 12,
        ),
        new FetchedCostPeriod(
            periodStart: CarbonImmutable::now()->startOfMonth(),
            periodEnd: CarbonImmutable::now()->startOfDay(),
            amount: 140.00,
            currency: 'USD',
            provider: CostSyncSource::Atlassian,
            seatCount: 14,
        ),
    ];

    $result = app(SyncAssetCosts::class)->handle($software);

    $software->refresh();

    expect($result['snapshots'])->toBe(2)
        ->and($software->billing_amount)->toBe('140.00')
        ->and($software->is_recurring)->toBeTrue()
        ->and($software->billing_interval)->toBe(SoftwareBillingInterval::Monthly)
        ->and($software->total_seats)->toBe(14)
        ->and($software->cost_synced_at)->not->toBeNull()
        ->and($software->cost_sync_error)->toBeNull()
        ->and(CostSnapshot::query()->where('costable_id', $software->id)->count())->toBe(2);
});

test('syncing cloud tenant costs updates current billing fields', function () {
    [, $organization] = actingAsOrganizationMember();

    $tenant = CloudTenant::factory()->aws()->withCredentials()->create([
        'organization_id' => $organization->id,
        'provider' => CloudTenantProvider::Aws,
        'cost_sync_provider' => CloudTenantCostSyncProvider::Native,
    ]);

    $this->fakeFetcher->periods = [
        new FetchedCostPeriod(
            periodStart: CarbonImmutable::now()->startOfMonth(),
            periodEnd: CarbonImmutable::now()->startOfDay(),
            amount: 88.25,
            currency: 'USD',
            provider: CostSyncSource::Aws,
        ),
    ];

    app(SyncAssetCosts::class)->handle($tenant);

    $tenant->refresh();

    expect($tenant->billing_amount)->toBe('88.25')
        ->and($tenant->currency)->toBe('USD')
        ->and($tenant->billing_interval)->toBe(SoftwareBillingInterval::Monthly)
        ->and($tenant->cost_synced_at)->not->toBeNull();
});

test('failed cost sync stores the error message', function () {
    [, $organization] = actingAsOrganizationMember();

    $software = Software::factory()->create([
        'organization_id' => $organization->id,
        'license_type' => SoftwareLicenseType::Subscription,
        'cost_sync_provider' => SoftwareCostSyncProvider::Cursor,
        'cost_sync_credentials' => [
            'team_id' => 'team-1',
            'api_key' => 'key',
        ],
    ]);

    $this->fakeFetcher->shouldFail = true;

    expect(fn () => app(SyncAssetCosts::class)->handle($software))
        ->toThrow(RuntimeException::class);

    expect($software->fresh()->cost_sync_error)->toBe('Provider denied access.');
});
