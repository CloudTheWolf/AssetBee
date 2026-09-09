<?php

namespace App\Services\Costs;

use App\Contracts\Costs\FetchesAssetCosts;
use App\Data\FetchedCostPeriod;
use App\Enums\AtlassianAddonSeatSource;
use App\Enums\AtlassianCostProduct;
use App\Enums\CostSyncSource;
use App\Enums\SoftwareCostSyncProvider;
use App\Models\CloudTenant;
use App\Models\Software;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
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
        $apiToken = (string) ($credentials['api_token'] ?? '');

        if ($organizationId === '' || $apiToken === '') {
            throw new RuntimeException(__('Atlassian cost sync credentials are incomplete.'));
        }

        $client = Http::withToken($apiToken)
            ->acceptJson()
            ->timeout(30);

        $productConfigs = AtlassianCostProduct::configsFromCredentials($credentials);
        $hostConfigs = array_values(array_filter(
            $productConfigs,
            static fn (array $config): bool => ! $config['custom'],
        ));

        [$hostSeatCounts, $uniqueUserCount] = $this->countSeatsByProduct($client, $organizationId, $hostConfigs);

        $products = [];
        $totalAmount = 0.0;

        foreach ($productConfigs as $config) {
            $seats = $this->resolveSeatCount($config, $hostSeatCounts);
            $amount = round($seats * $config['price_per_seat'], 2);
            $totalAmount += $amount;

            $products[] = [
                'slug' => $config['slug'],
                'label' => $config['label'],
                'price_per_seat' => $config['price_per_seat'],
                'seats' => $seats,
                'amount' => $amount,
                'child_software_id' => $config['child_software_id'],
                'custom' => $config['custom'],
                'keys' => $config['keys'],
                'name_contains' => $config['name_contains'],
                'seat_source' => $config['seat_source'],
                'manual_seats' => $config['manual_seats'],
            ];
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
                amount: round($totalAmount, 2),
                currency: strtoupper($asset->currency ?: 'USD'),
                provider: CostSyncSource::Atlassian,
                seatCount: $uniqueUserCount > 0 ? $uniqueUserCount : null,
                meta: [
                    'source' => 'atlassian_product_seats',
                    'products' => $products,
                ],
            ),
        ];
    }

    /**
     * @param  array{
     *     slug: string,
     *     custom: bool,
     *     seat_source: string|null,
     *     manual_seats: int|null
     * }  $config
     * @param  array<string, int>  $hostSeatCounts
     */
    protected function resolveSeatCount(array $config, array $hostSeatCounts): int
    {
        if (! $config['custom']) {
            return $hostSeatCounts[$config['slug']] ?? 0;
        }

        $seatSource = AtlassianAddonSeatSource::tryFrom((string) ($config['seat_source'] ?? ''))
            ?? AtlassianAddonSeatSource::Jira;

        if ($seatSource === AtlassianAddonSeatSource::Manual) {
            return max(0, (int) ($config['manual_seats'] ?? 0));
        }

        $host = $seatSource->hostProduct();

        return $host === null ? 0 : ($hostSeatCounts[$host->value] ?? 0);
    }

    /**
     * @param  list<array{slug: string, keys: list<string>, name_contains: string|null}>  $productConfigs
     * @return array{0: array<string, int>, 1: int}
     */
    protected function countSeatsByProduct(PendingRequest $client, string $organizationId, array $productConfigs): array
    {
        $counts = [];
        foreach ($productConfigs as $config) {
            $counts[$config['slug']] = 0;
        }

        $uniqueUsers = [];
        $url = "https://api.atlassian.com/admin/v1/orgs/{$organizationId}/users";
        $pages = 0;

        while ($url !== null && $pages < 50) {
            $pages++;
            $response = $client->get($url);

            if (! $response->successful()) {
                throw new RuntimeException(__('Atlassian Admin API request failed with status :status.', [
                    'status' => $response->status(),
                ]));
            }

            $users = $response->json('data');
            if (! is_array($users)) {
                $users = [];
            }

            foreach ($users as $user) {
                if (! $this->userIsActive($user)) {
                    continue;
                }

                $matched = $this->matchedProductSlugs($user, $productConfigs);
                if ($matched === []) {
                    continue;
                }

                $accountId = (string) (is_array($user) ? ($user['account_id'] ?? '') : '');
                if ($accountId !== '') {
                    $uniqueUsers[$accountId] = true;
                }

                foreach ($matched as $slug) {
                    $counts[$slug]++;
                }
            }

            $next = $response->json('links.next');
            $url = $this->nextUsersUrl($next, $organizationId);
        }

        return [$counts, count($uniqueUsers)];
    }

    /**
     * @param  list<array{slug: string, keys: list<string>, name_contains: string|null}>  $productConfigs
     * @return list<string>
     */
    protected function matchedProductSlugs(mixed $user, array $productConfigs): array
    {
        if (! is_array($user)) {
            return [];
        }

        $productAccess = $user['product_access'] ?? [];
        if (! is_array($productAccess) || $productAccess === []) {
            return [];
        }

        $matched = [];

        foreach ($productConfigs as $config) {
            foreach ($productAccess as $access) {
                if (! is_array($access)) {
                    continue;
                }

                $key = strtolower(trim((string) ($access['key'] ?? '')));
                $name = (string) ($access['name'] ?? '');

                if ($key !== '' && in_array($key, $config['keys'], true)) {
                    $matched[] = $config['slug'];
                    break;
                }

                $needle = $config['name_contains'] ?? null;
                if (is_string($needle) && $needle !== '' && str_contains(strtolower($name), strtolower($needle))) {
                    $matched[] = $config['slug'];
                    break;
                }
            }
        }

        return array_values(array_unique($matched));
    }

    protected function userIsActive(mixed $user): bool
    {
        if (! is_array($user)) {
            return false;
        }

        $status = strtolower((string) ($user['account_status'] ?? 'active'));

        return $status === '' || $status === 'active';
    }

    protected function nextUsersUrl(mixed $next, string $organizationId): ?string
    {
        if (! is_string($next) || $next === '') {
            return null;
        }

        if (str_starts_with($next, 'http://') || str_starts_with($next, 'https://')) {
            return $next;
        }

        return 'https://api.atlassian.com/admin/v1/orgs/'.$organizationId.'/users?cursor='.urlencode($next);
    }
}
