<?php

namespace App\Services\Costs;

use App\Contracts\Costs\FetchesAssetCosts;
use App\Data\FetchedCostPeriod;
use App\Enums\CostSyncSource;
use App\Enums\SoftwareCostSyncProvider;
use App\Models\CloudTenant;
use App\Models\Software;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AtlassianCostFetcher implements FetchesAssetCosts
{
    public function supports(Software|CloudTenant $asset): bool
    {
        return $asset instanceof Software
            && $asset->cost_sync_provider === SoftwareCostSyncProvider::Atlassian
            && $asset->hasCostSyncCredentials();
    }

    public function fetch(Software|CloudTenant $asset, CarbonInterface $from, CarbonInterface $to): array
    {
        /** @var Software $asset */
        $credentials = $asset->cost_sync_credentials ?? [];
        $organizationId = (string) ($credentials['organization_id'] ?? '');
        $email = (string) ($credentials['email'] ?? '');
        $apiToken = (string) ($credentials['api_token'] ?? '');

        if ($organizationId === '' || $email === '' || $apiToken === '') {
            throw new RuntimeException(__('Atlassian cost sync credentials are incomplete.'));
        }

        $response = Http::withBasicAuth($email, $apiToken)
            ->acceptJson()
            ->timeout(30)
            ->get("https://api.atlassian.com/admin/v1/orgs/{$organizationId}/billing-details");

        if (! $response->successful()) {
            // Fallback: licenses endpoint for seat counts when billing details are unavailable.
            $licenses = Http::withBasicAuth($email, $apiToken)
                ->acceptJson()
                ->timeout(30)
                ->get("https://api.atlassian.com/admin/v1/orgs/{$organizationId}/licenses");

            if (! $licenses->successful()) {
                throw new RuntimeException(__('Atlassian Admin API request failed with status :status.', [
                    'status' => $response->status(),
                ]));
            }

            $seatCount = collect($licenses->json('data') ?? $licenses->json() ?? [])
                ->sum(fn ($row): int => (int) data_get($row, 'count', data_get($row, 'seats', 0)));

            $amount = is_numeric($asset->billing_amount) ? (float) $asset->billing_amount : 0.0;
            $periodStart = CarbonImmutable::parse($to)->startOfMonth()->startOfDay();
            $periodEnd = CarbonImmutable::parse($to)->endOfMonth()->startOfDay();

            return [
                new FetchedCostPeriod(
                    periodStart: $periodStart,
                    periodEnd: $periodEnd > now() ? now()->startOfDay() : $periodEnd,
                    amount: round($amount, 2),
                    currency: strtoupper($asset->currency ?: 'USD'),
                    provider: CostSyncSource::Atlassian,
                    seatCount: $seatCount > 0 ? $seatCount : null,
                    meta: ['source' => 'atlassian_licenses'],
                ),
            ];
        }

        $payload = $response->json() ?? [];
        $amount = (float) data_get($payload, 'currentBill.amount', data_get($payload, 'amount', 0));
        $currency = strtoupper((string) data_get($payload, 'currentBill.currency', data_get($payload, 'currency', 'USD')));
        $seatCount = data_get($payload, 'seats');
        $seatCount = is_numeric($seatCount) ? (int) $seatCount : null;

        $periodStart = CarbonImmutable::parse($to)->startOfMonth()->startOfDay();
        $periodEnd = CarbonImmutable::parse($to)->endOfMonth()->startOfDay();
        if ($periodEnd->greaterThan(now())) {
            $periodEnd = now()->startOfDay();
        }

        return [
            new FetchedCostPeriod(
                periodStart: $periodStart,
                periodEnd: $periodEnd,
                amount: round($amount, 2),
                currency: $currency !== '' ? $currency : 'USD',
                provider: CostSyncSource::Atlassian,
                seatCount: $seatCount,
                meta: ['source' => 'atlassian_billing'],
            ),
        ];
    }
}
