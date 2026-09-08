<?php

namespace App\Actions\Assets;

use App\Data\FetchedCostPeriod;
use App\Enums\SoftwareBillingInterval;
use App\Enums\SoftwareLicenseType;
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
}
