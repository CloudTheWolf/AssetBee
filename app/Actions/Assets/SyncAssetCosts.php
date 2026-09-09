<?php

namespace App\Actions\Assets;

use App\Data\FetchedCostPeriod;
use App\Enums\AtlassianAddonSeatSource;
use App\Enums\AtlassianCostProduct;
use App\Enums\SoftwareBillingInterval;
use App\Enums\SoftwareCostSyncProvider;
use App\Enums\SoftwareLicenseType;
use App\Enums\SoftwareStatus;
use App\Models\CloudTenant;
use App\Models\CostSnapshot;
use App\Models\Software;
use App\Services\Costs\CostFetchManager;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class SyncAssetCosts
{
    public function __construct(
        protected CostFetchManager $costFetchManager,
    ) {}

    /**
     * @return array{snapshots: int, amount: float|null, currency: string|null, seat_count: int|null}
     */
    public function handle(Software|CloudTenant $asset, int $months = 12): array
    {
        $months = max(1, min(24, $months));
        $to = CarbonImmutable::now()->startOfDay();
        $from = $to->startOfMonth()->subMonthsNoOverflow($months - 1)->startOfDay();

        try {
            if (! $this->costFetchManager->supports($asset)) {
                throw new RuntimeException(__('Cost sync is not configured for this asset.'));
            }

            $periods = $this->costFetchManager->fetch($asset, $from, $to);

            return DB::transaction(function () use ($asset, $periods): array {
                $written = 0;
                foreach ($periods as $period) {
                    $this->upsertSnapshot($asset, $period);
                    $written++;
                }

                $latest = $this->latestPeriod($periods);
                $this->updateCurrentTotals($asset, $latest);

                if ($asset instanceof Software && $latest !== null) {
                    $written += $this->syncAtlassianProductChildren($asset, $latest);
                }

                $asset->update([
                    'cost_synced_at' => now(),
                    'cost_sync_error' => null,
                ]);

                return [
                    'snapshots' => $written,
                    'amount' => $latest?->amount,
                    'currency' => $latest?->currency,
                    'seat_count' => $latest?->seatCount,
                ];
            });
        } catch (Throwable $exception) {
            $asset->update([
                'cost_sync_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    protected function upsertSnapshot(Software|CloudTenant $asset, FetchedCostPeriod $period): void
    {
        CostSnapshot::query()->updateOrCreate(
            [
                'costable_type' => $asset::class,
                'costable_id' => $asset->id,
                'period_start' => $period->periodStart->toDateString(),
                'period_end' => $period->periodEnd->toDateString(),
            ],
            [
                'organization_id' => $asset->organization_id,
                'amount' => $period->amount,
                'currency' => strtoupper($period->currency),
                'seat_count' => $period->seatCount,
                'provider' => $period->provider,
                'meta' => $period->meta === [] ? null : $period->meta,
                'synced_at' => now(),
            ],
        );
    }

    /**
     * @param  list<FetchedCostPeriod>  $periods
     */
    protected function latestPeriod(array $periods): ?FetchedCostPeriod
    {
        if ($periods === []) {
            return null;
        }

        usort(
            $periods,
            fn (FetchedCostPeriod $a, FetchedCostPeriod $b): int => $b->periodEnd <=> $a->periodEnd,
        );

        return $periods[0];
    }

    protected function updateCurrentTotals(Software|CloudTenant $asset, ?FetchedCostPeriod $latest): void
    {
        if ($latest === null) {
            return;
        }

        if ($asset instanceof Software) {
            $attributes = [
                'is_recurring' => true,
                'billing_interval' => $asset->billing_interval ?? SoftwareBillingInterval::Monthly,
                'billing_amount' => $latest->amount,
                'currency' => strtoupper($latest->currency),
            ];

            if ($latest->seatCount !== null && $asset->license_type === SoftwareLicenseType::Seat) {
                $attributes['total_seats'] = $latest->seatCount;
            }

            $asset->update($attributes);

            return;
        }

        $asset->update([
            'billing_amount' => $latest->amount,
            'currency' => strtoupper($latest->currency),
            'billing_interval' => $asset->billing_interval ?? SoftwareBillingInterval::Monthly,
        ]);
    }

    /**
     * Create/update sub-software rows for each Atlassian product and write their cost snapshots.
     */
    protected function syncAtlassianProductChildren(Software $parent, FetchedCostPeriod $period): int
    {
        if ($parent->cost_sync_provider !== SoftwareCostSyncProvider::Atlassian) {
            return 0;
        }

        $products = $period->meta['products'] ?? null;
        if (! is_array($products) || $products === []) {
            return 0;
        }

        $credentials = $parent->cost_sync_credentials ?? [];
        $storedProducts = is_array($credentials['products'] ?? null) ? $credentials['products'] : [];
        $storedAddons = is_array($credentials['addons'] ?? null) ? $credentials['addons'] : [];
        $written = 0;

        foreach ($products as $product) {
            if (! is_array($product) || blank($product['slug'] ?? null)) {
                continue;
            }

            $slug = (string) $product['slug'];
            $enum = AtlassianCostProduct::tryFrom($slug);
            $isCustom = $enum === null || (bool) ($product['custom'] ?? false);
            $label = (string) ($product['label'] ?? $enum?->label() ?? $slug);
            $seats = max(0, (int) ($product['seats'] ?? 0));
            $amount = round((float) ($product['amount'] ?? 0), 2);
            $currency = strtoupper($period->currency);

            $child = $this->resolveAtlassianChildSoftware($parent, $slug, $label, $product);
            $child->update([
                'name' => $label,
                'vendor' => $isCustom ? 'Atlassian Marketplace' : ($parent->vendor ?: 'Atlassian'),
                'parent_software_id' => $parent->id,
                'license_type' => SoftwareLicenseType::Seat,
                'total_seats' => $seats,
                'status' => SoftwareStatus::Active,
                'is_recurring' => true,
                'billing_interval' => SoftwareBillingInterval::Monthly,
                'billing_amount' => $amount,
                'currency' => $currency,
                'cost_synced_at' => now(),
                'cost_sync_error' => null,
            ]);

            $childPeriod = new FetchedCostPeriod(
                periodStart: $period->periodStart,
                periodEnd: $period->periodEnd,
                amount: $amount,
                currency: $currency,
                provider: $period->provider,
                seatCount: $seats,
                meta: [
                    'source' => 'atlassian_product_child',
                    'product' => $slug,
                    'price_per_seat' => $product['price_per_seat'] ?? null,
                    'custom' => $isCustom,
                ],
            );
            $this->upsertSnapshot($child, $childPeriod);
            $written++;

            if ($isCustom) {
                $updated = false;
                foreach ($storedAddons as $index => $addon) {
                    if (! is_array($addon)) {
                        continue;
                    }

                    if ((string) ($addon['slug'] ?? '') !== $slug) {
                        continue;
                    }

                    $storedAddons[$index] = array_merge($addon, [
                        'price_per_seat' => $product['price_per_seat'] ?? ($addon['price_per_seat'] ?? 0),
                        'seat_source' => $product['seat_source'] ?? ($addon['seat_source'] ?? AtlassianAddonSeatSource::Jira->value),
                        'manual_seats' => $product['manual_seats'] ?? ($addon['manual_seats'] ?? null),
                        'child_software_id' => $child->id,
                    ]);
                    $updated = true;
                    break;
                }

                if (! $updated) {
                    $storedAddons[] = [
                        'slug' => $slug,
                        'label' => $label,
                        'price_per_seat' => $product['price_per_seat'] ?? 0,
                        'keys' => $product['keys'] ?? [],
                        'seat_source' => $product['seat_source'] ?? AtlassianAddonSeatSource::Jira->value,
                        'manual_seats' => $product['manual_seats'] ?? null,
                        'child_software_id' => $child->id,
                    ];
                }
            } else {
                $storedProducts[$slug] = array_merge(
                    is_array($storedProducts[$slug] ?? null) ? $storedProducts[$slug] : [],
                    [
                        'price_per_seat' => $product['price_per_seat'] ?? ($storedProducts[$slug]['price_per_seat'] ?? 0),
                        'child_software_id' => $child->id,
                    ],
                );
            }
        }

        $credentials['products'] = $storedProducts;
        $credentials['addons'] = array_values($storedAddons);
        unset($credentials['products']['git_integration_for_jira']);
        $parent->update(['cost_sync_credentials' => $credentials]);
        $parent->load('childSoftwares');

        return $written;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    protected function resolveAtlassianChildSoftware(Software $parent, string $slug, string $label, array $product): Software
    {
        $childId = $product['child_software_id'] ?? null;
        if (is_numeric($childId)) {
            $existing = Software::query()
                ->where('organization_id', $parent->organization_id)
                ->whereKey((int) $childId)
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $existing = Software::query()
            ->where('organization_id', $parent->organization_id)
            ->where('parent_software_id', $parent->id)
            ->where('name', $label)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return Software::query()->create([
            'organization_id' => $parent->organization_id,
            'parent_software_id' => $parent->id,
            'name' => $label,
            'vendor' => $parent->vendor ?: 'Atlassian',
            'license_type' => SoftwareLicenseType::Seat,
            'total_seats' => 0,
            'status' => SoftwareStatus::Active,
            'is_recurring' => true,
            'billing_interval' => SoftwareBillingInterval::Monthly,
            'billing_amount' => 0,
            'currency' => strtoupper($parent->currency ?: 'USD'),
            'cost_sync_provider' => SoftwareCostSyncProvider::None,
        ]);
    }
}
