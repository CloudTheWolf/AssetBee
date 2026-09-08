<?php

namespace App\Services\Costs;

use App\Contracts\Costs\FetchesAssetCosts;
use App\Data\FetchedCostPeriod;
use App\Enums\CloudTenantCostSyncProvider;
use App\Enums\CloudTenantProvider;
use App\Enums\CostSyncSource;
use App\Models\CloudTenant;
use App\Models\Software;
use Aws\CostExplorer\CostExplorerClient;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use RuntimeException;

class AwsCostFetcher implements FetchesAssetCosts
{
    public function supports(Software|CloudTenant $asset): bool
    {
        return $asset instanceof CloudTenant
            && $asset->cost_sync_provider === CloudTenantCostSyncProvider::Native
            && $asset->provider === CloudTenantProvider::Aws
            && $asset->hasCredentials();
    }

    public function fetch(Software|CloudTenant $asset, CarbonInterface $from, CarbonInterface $to): array
    {
        /** @var CloudTenant $asset */
        $credentials = $asset->credentials ?? [];
        $client = $this->makeClient($credentials);

        $start = CarbonImmutable::parse($from)->startOfMonth()->toDateString();
        $end = CarbonImmutable::parse($to)->addDay()->toDateString();

        try {
            $result = $client->getCostAndUsage([
                'TimePeriod' => [
                    'Start' => $start,
                    'End' => $end,
                ],
                'Granularity' => 'MONTHLY',
                'Metrics' => ['UnblendedCost'],
            ]);
        } catch (\Throwable $exception) {
            throw new RuntimeException(__('AWS Cost Explorer request failed: :message', [
                'message' => $exception->getMessage(),
            ]), previous: $exception);
        }

        /** @var list<FetchedCostPeriod> $periods */
        $periods = [];

        foreach (($result['ResultsByTime'] ?? []) as $row) {
            $periodStart = CarbonImmutable::parse($row['TimePeriod']['Start'] ?? $start)->startOfDay();
            $periodEnd = CarbonImmutable::parse($row['TimePeriod']['End'] ?? $end)->subDay()->startOfDay();
            $amount = (float) ($row['Total']['UnblendedCost']['Amount'] ?? 0);
            $currency = strtoupper((string) ($row['Total']['UnblendedCost']['Unit'] ?? 'USD'));

            $periods[] = new FetchedCostPeriod(
                periodStart: $periodStart,
                periodEnd: $periodEnd,
                amount: round($amount, 2),
                currency: $currency !== '' ? $currency : 'USD',
                provider: CostSyncSource::Aws,
                meta: ['metric' => 'UnblendedCost'],
            );
        }

        return $periods;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    protected function makeClient(array $credentials): CostExplorerClient
    {
        $config = [
            'version' => 'latest',
            'region' => (string) ($credentials['region'] ?? 'us-east-1'),
            'credentials' => [
                'key' => (string) ($credentials['access_key_id'] ?? ''),
                'secret' => (string) ($credentials['secret_access_key'] ?? ''),
            ],
        ];

        if (filled($credentials['session_token'] ?? null)) {
            $config['credentials']['token'] = (string) $credentials['session_token'];
        }

        return new CostExplorerClient($config);
    }
}
