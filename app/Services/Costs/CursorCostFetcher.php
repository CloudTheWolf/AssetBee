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

class CursorCostFetcher implements FetchesAssetCosts
{
    public function supports(Software|CloudTenant $asset): bool
    {
        return $asset instanceof Software
            && $asset->cost_sync_provider === SoftwareCostSyncProvider::Cursor
            && $asset->hasCostSyncCredentials();
    }

    public function fetch(Software|CloudTenant $asset, CarbonInterface $from, CarbonInterface $to): array
    {
        /** @var Software $asset */
        $credentials = $asset->cost_sync_credentials ?? [];
        $teamId = (string) ($credentials['team_id'] ?? '');
        $apiKey = (string) ($credentials['api_key'] ?? '');

        if ($teamId === '' || $apiKey === '') {
            throw new RuntimeException(__('Cursor cost sync credentials are incomplete.'));
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(30)
            ->get("https://api.cursor.com/teams/{$teamId}/billing");

        if (! $response->successful()) {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(30)
                ->get("https://api.cursor.com/teams/{$teamId}");
        }

        if (! $response->successful()) {
            throw new RuntimeException(__('Cursor API request failed with status :status.', [
                'status' => $response->status(),
            ]));
        }

        $payload = $response->json() ?? [];
        $amount = (float) data_get($payload, 'billing.amount', data_get($payload, 'amount', data_get($payload, 'monthlySpend', 0)));
        $currency = strtoupper((string) data_get($payload, 'billing.currency', data_get($payload, 'currency', $asset->currency ?: 'USD')));
        $seatCount = data_get($payload, 'seats', data_get($payload, 'memberCount', data_get($payload, 'usage.seats')));
        $seatCount = is_numeric($seatCount) ? (int) $seatCount : null;

        if ($amount <= 0 && is_numeric($asset->billing_amount)) {
            $amount = (float) $asset->billing_amount;
        }

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
                provider: CostSyncSource::Cursor,
                seatCount: $seatCount,
                meta: ['source' => 'cursor_team'],
            ),
        ];
    }
}
