<?php

use App\Contracts\Costs\FetchesAssetCosts;
use App\Data\FetchedCostPeriod;
use App\Enums\CostSyncSource;
use App\Enums\SoftwareCostSyncProvider;
use App\Models\CloudTenant;
use App\Models\Software;
use App\Services\Costs\CostFetchManager;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Artisan;

test('cost sync command only syncs assets for organizations with cost sync enabled', function () {
    $synced = [];

    $fakeFetcher = new class($synced) implements FetchesAssetCosts
    {
        /** @param  list<int>  $synced */
        public function __construct(public array &$synced) {}

        public function supports(Software|CloudTenant $asset): bool
        {
            return $asset->hasCostSyncConfigured();
        }

        public function fetch(Software|CloudTenant $asset, CarbonInterface $from, CarbonInterface $to): array
        {
            $this->synced[] = $asset->id;

            return [
                new FetchedCostPeriod(
                    periodStart: CarbonImmutable::now()->startOfMonth(),
                    periodEnd: CarbonImmutable::now()->startOfDay(),
                    amount: 10.00,
                    currency: 'USD',
                    provider: CostSyncSource::CustomHttp,
                ),
            ];
        }
    };

    $this->app->instance(
        CostFetchManager::class,
        new CostFetchManager([$fakeFetcher]),
    );

    [, $enabledOrganization] = actingAsOrganizationMember();
    $enabledOrganization->update(['cost_sync_enabled' => true]);

    [, $disabledOrganization] = actingAsOrganizationMember();
    $disabledOrganization->update(['cost_sync_enabled' => false]);

    $enabledSoftware = Software::factory()->create([
        'organization_id' => $enabledOrganization->id,
        'cost_sync_provider' => SoftwareCostSyncProvider::CustomHttp,
        'cost_sync_request' => [
            'method' => 'GET',
            'url' => 'https://example.test/cost',
            'response_amount_path' => 'amount',
        ],
        'cost_sync_credentials' => ['bearer_token' => 'x'],
    ]);

    Software::factory()->create([
        'organization_id' => $disabledOrganization->id,
        'cost_sync_provider' => SoftwareCostSyncProvider::CustomHttp,
        'cost_sync_request' => [
            'method' => 'GET',
            'url' => 'https://example.test/cost',
            'response_amount_path' => 'amount',
        ],
        'cost_sync_credentials' => ['bearer_token' => 'x'],
    ]);

    Artisan::call('cost:sync');

    expect($synced)->toBe([$enabledSoftware->id])
        ->and($enabledSoftware->fresh()->billing_amount)->toBe('10.00');
});

test('cost sync command --all includes organizations with automatic sync disabled', function () {
    $synced = [];

    $fakeFetcher = new class($synced) implements FetchesAssetCosts
    {
        /** @param  list<int>  $synced */
        public function __construct(public array &$synced) {}

        public function supports(Software|CloudTenant $asset): bool
        {
            return $asset->hasCostSyncConfigured();
        }

        public function fetch(Software|CloudTenant $asset, CarbonInterface $from, CarbonInterface $to): array
        {
            $this->synced[] = $asset->id;

            return [
                new FetchedCostPeriod(
                    periodStart: CarbonImmutable::now()->startOfMonth(),
                    periodEnd: CarbonImmutable::now()->startOfDay(),
                    amount: 12.00,
                    currency: 'USD',
                    provider: CostSyncSource::CustomHttp,
                ),
            ];
        }
    };

    $this->app->instance(
        CostFetchManager::class,
        new CostFetchManager([$fakeFetcher]),
    );

    [, $organization] = actingAsOrganizationMember();
    $organization->update(['cost_sync_enabled' => false]);

    $software = Software::factory()->create([
        'organization_id' => $organization->id,
        'cost_sync_provider' => SoftwareCostSyncProvider::CustomHttp,
        'cost_sync_request' => [
            'method' => 'GET',
            'url' => 'https://example.test/cost',
            'response_amount_path' => 'amount',
        ],
        'cost_sync_credentials' => ['bearer_token' => 'x'],
    ]);

    Artisan::call('cost:sync', ['--all' => true]);

    expect($synced)->toBe([$software->id]);
});
