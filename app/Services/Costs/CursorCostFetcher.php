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
        $apiKey = (string) ($credentials['api_key'] ?? '');

        if ($apiKey === '') {
            throw new RuntimeException(__('Cursor cost sync credentials are incomplete.'));
        }

        $page = 1;
        $pageSize = 100;
        $totalPages = 1;
        $totalMembers = null;
        $subscriptionCycleStartMs = null;
        $overallSpendCents = 0.0;

        do {
            $response = Http::withBasicAuth($apiKey, '')
                ->acceptJson()
                ->asJson()
                ->timeout(30)
                ->post('https://api.cursor.com/teams/spend', [
                    'page' => $page,
                    'pageSize' => $pageSize,
                ]);

            if (! $response->successful()) {
                throw new RuntimeException(__('Cursor API request failed with status :status.', [
                    'status' => $response->status(),
                ]));
            }

            $payload = $response->json() ?? [];
            $members = data_get($payload, 'teamMemberSpend', []);
            if (! is_array($members)) {
                $members = [];
            }

            foreach ($members as $member) {
                if (! is_array($member)) {
                    continue;
                }

                $overallSpendCents += (float) ($member['overallSpendCents'] ?? $member['spendCents'] ?? 0);
            }

            $totalPages = max(1, (int) data_get($payload, 'totalPages', 1));
            $totalMembers = is_numeric(data_get($payload, 'totalMembers'))
                ? (int) data_get($payload, 'totalMembers')
                : $totalMembers;
            $subscriptionCycleStartMs = is_numeric(data_get($payload, 'subscriptionCycleStart'))
                ? (int) data_get($payload, 'subscriptionCycleStart')
                : $subscriptionCycleStartMs;

            $page++;
        } while ($page <= $totalPages);

        $amount = round($overallSpendCents / 100, 2);
        $currency = strtoupper($asset->currency ?: 'USD');
        $seatCount = is_int($totalMembers) ? $totalMembers : null;

        if ($subscriptionCycleStartMs !== null && $subscriptionCycleStartMs > 0) {
            $periodStart = CarbonImmutable::createFromTimestampMs($subscriptionCycleStartMs)->startOfDay();
            $periodEnd = now()->startOfDay();
            if ($periodEnd->lessThan($periodStart)) {
                $periodEnd = $periodStart;
            }
        } else {
            $periodStart = CarbonImmutable::parse($to)->startOfMonth()->startOfDay();
            $periodEnd = CarbonImmutable::parse($to)->endOfMonth()->startOfDay();
            if ($periodEnd->greaterThan(now())) {
                $periodEnd = now()->startOfDay();
            }
        }

        return [
            new FetchedCostPeriod(
                periodStart: $periodStart,
                periodEnd: $periodEnd,
                amount: $amount,
                currency: $currency !== '' ? $currency : 'USD',
                provider: CostSyncSource::Cursor,
                seatCount: $seatCount,
                meta: ['source' => 'cursor_teams_spend'],
            ),
        ];
    }
}
