<?php

namespace App\Services\Costs;

use App\Contracts\Costs\FetchesAssetCosts;
use App\Data\FetchedCostPeriod;
use App\Enums\CloudTenantCostSyncProvider;
use App\Enums\CloudTenantProvider;
use App\Enums\CostSyncSource;
use App\Models\CloudTenant;
use App\Models\Software;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AzureCostFetcher implements FetchesAssetCosts
{
    public function supports(Software|CloudTenant $asset): bool
    {
        return $asset instanceof CloudTenant
            && $asset->cost_sync_provider === CloudTenantCostSyncProvider::Native
            && $asset->provider === CloudTenantProvider::Azure
            && $asset->hasCredentials();
    }

    public function fetch(Software|CloudTenant $asset, CarbonInterface $from, CarbonInterface $to): array
    {
        /** @var CloudTenant $asset */
        $credentials = $asset->credentials ?? [];
        $token = $this->accessToken($credentials);
        $subscriptionId = (string) ($credentials['subscription_id'] ?? '');

        if ($subscriptionId === '') {
            throw new RuntimeException(__('Azure subscription ID is required for cost sync.'));
        }

        $start = CarbonImmutable::parse($from)->startOfMonth();
        $end = CarbonImmutable::parse($to)->endOfMonth();

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(60)
            ->post("https://management.azure.com/subscriptions/{$subscriptionId}/providers/Microsoft.CostManagement/query?api-version=2023-11-01", [
                'type' => 'ActualCost',
                'timeframe' => 'Custom',
                'timePeriod' => [
                    'from' => $start->toIso8601String(),
                    'to' => $end->toIso8601String(),
                ],
                'dataset' => [
                    'granularity' => 'Monthly',
                    'aggregation' => [
                        'totalCost' => [
                            'name' => 'Cost',
                            'function' => 'Sum',
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(__('Azure Cost Management request failed with status :status.', [
                'status' => $response->status(),
            ]));
        }

        $columnRows = $response->json('properties.columns');
        if (! is_array($columnRows)) {
            $columnRows = [];
        }

        /** @var list<string> $columns */
        $columns = [];
        foreach ($columnRows as $column) {
            if (! is_array($column)) {
                continue;
            }

            $columns[] = strtolower((string) ($column['name'] ?? ''));
        }

        $rows = $response->json('properties.rows');
        if (! is_array($rows)) {
            $rows = [];
        }

        /** @var list<FetchedCostPeriod> $periods */
        $periods = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $mapped = [];
            foreach ($columns as $index => $name) {
                $mapped[$name] = $row[$index] ?? null;
            }

            $amount = (float) ($mapped['cost'] ?? $mapped['totalcost'] ?? 0);
            $currency = strtoupper((string) ($mapped['currency'] ?? 'USD'));
            $billingMonth = (string) ($mapped['billingmonth'] ?? $mapped['usagedate'] ?? $start->toDateString());
            $periodStart = CarbonImmutable::parse($billingMonth)->startOfMonth()->startOfDay();
            $periodEnd = $periodStart->endOfMonth()->startOfDay();

            $periods[] = new FetchedCostPeriod(
                periodStart: $periodStart,
                periodEnd: $periodEnd,
                amount: round($amount, 2),
                currency: $currency !== '' ? $currency : 'USD',
                provider: CostSyncSource::Azure,
                meta: ['source' => 'azure_cost_management'],
            );
        }

        return $periods;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    protected function accessToken(array $credentials): string
    {
        $tenantId = (string) ($credentials['tenant_id'] ?? '');
        $clientId = (string) ($credentials['client_id'] ?? '');
        $clientSecret = (string) ($credentials['client_secret'] ?? '');

        $response = Http::asForm()
            ->timeout(30)
            ->post("https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token", [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'scope' => 'https://management.azure.com/.default',
            ]);

        if (! $response->successful() || blank($response->json('access_token'))) {
            throw new RuntimeException(__('Unable to authenticate with Azure for cost sync.'));
        }

        return (string) $response->json('access_token');
    }
}
