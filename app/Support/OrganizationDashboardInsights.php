<?php

namespace App\Support;

use App\Enums\CloudTenantStatus;
use App\Enums\HardwareStatus;
use App\Enums\SoftwareStatus;
use App\Models\CloudTenant;
use App\Models\CostSnapshot;
use App\Models\Hardware;
use App\Models\Organization;
use App\Models\Software;
use App\Models\Userware;
use App\Models\Virtualware;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class OrganizationDashboardInsights
{
    /**
     * @return array{
     *     inventory: array{userware: int, hardware: int, cloud_tenants: int, virtualware: int, software: int},
     *     unassigned_hardware: int,
     *     costs: array{
     *         currency: string,
     *         estimated_monthly: float,
     *         estimated_annual: float,
     *         upcoming_30_days: float,
     *         formatted_monthly: string,
     *         formatted_annual: string,
     *         formatted_upcoming_30_days: string,
     *         other_currencies: list<array{currency: string, estimated_monthly: float, formatted_monthly: string}>
     *     },
     *     monthly_forecast: list<array{
     *         key: string,
     *         label: string,
     *         mode: string,
     *         actual: float|null,
     *         estimated: float|null,
     *         total: float,
     *         formatted: string,
     *         formatted_actual: string|null,
     *         formatted_estimated: string|null,
     *         percent: float,
     *         actual_segment_percent: float,
     *         estimated_segment_percent: float,
     *         top_actual: list<array{name: string, amount: float, formatted: string}>,
     *         top_estimated: list<array{name: string, amount: float, formatted: string}>
     *     }>,
     *     monthly_forecast_y_axis: list<array{label: string}>,
     *     monthly_forecast_trend_points: string,
     *     top_costs: list<array{id: int, type: string, name: string, vendor: string|null, monthly: float, formatted: string, percent: float}>,
     *     upcoming_renewals: list<array{id: int, name: string, amount: float, formatted_amount: string, currency: string, next_billing_at: string}>,
     *     expiring_licenses: list<array{id: int, name: string, expires_at: string}>,
     *     underutilized_seats: list<array{id: int, name: string, used: int, total: int, unused: int}>
     * }
     */
    public function for(Organization $organization): array
    {
        $recurring = Software::query()
            ->roots()
            ->with(['childSoftwares' => function ($query): void {
                $query->where('status', SoftwareStatus::Active);
            }])
            ->where('organization_id', $organization->id)
            ->where('status', SoftwareStatus::Active)
            ->where(function ($query): void {
                $query->where(function ($own): void {
                    $own->where('is_recurring', true)
                        ->whereNotNull('billing_amount')
                        ->whereNotNull('billing_interval');
                })->orWhereHas('childSoftwares', function ($child): void {
                    $child->where('status', SoftwareStatus::Active)
                        ->where('is_recurring', true)
                        ->whereNotNull('billing_amount')
                        ->whereNotNull('billing_interval');
                });
            })
            ->orderBy('name')
            ->get();

        $cloudCosts = CloudTenant::query()
            ->where('organization_id', $organization->id)
            ->where('status', CloudTenantStatus::Active)
            ->whereNotNull('billing_amount')
            ->whereNotNull('billing_interval')
            ->orderBy('name')
            ->get();

        $seatLicenses = Software::query()
            ->withCount('assignments')
            ->where('organization_id', $organization->id)
            ->where('status', SoftwareStatus::Active)
            ->whereNotNull('total_seats')
            ->get();

        $primaryCurrency = $this->primaryCurrency($recurring, $cloudCosts);
        $primaryRecurring = $recurring
            ->filter(fn (Software $software): bool => $this->costCurrency($software) === $primaryCurrency)
            ->values();
        $primaryCloudCosts = $cloudCosts
            ->filter(fn (CloudTenant $tenant): bool => $this->currencyCode($tenant->currency) === $primaryCurrency)
            ->values();

        $softwareMonthly = round($primaryRecurring->sum(fn (Software $software): float => $this->effectiveMonthlyCost($software)), 2);
        $cloudMonthly = round($primaryCloudCosts->sum(fn (CloudTenant $tenant): float => $tenant->monthlyCost() ?? 0.0), 2);
        $estimatedMonthly = round($softwareMonthly + $cloudMonthly, 2);
        $estimatedAnnual = round($estimatedMonthly * 12, 2);
        $forecast = $this->monthlyForecast($organization, $primaryRecurring, $primaryCloudCosts, $primaryCurrency);
        $upcoming30Days = $this->upcomingBillingTotal($primaryRecurring, $primaryCloudCosts, 30);

        return [
            'inventory' => [
                'userware' => Userware::query()->where('organization_id', $organization->id)->count(),
                'hardware' => Hardware::query()->where('organization_id', $organization->id)->count(),
                'cloud_tenants' => CloudTenant::query()->where('organization_id', $organization->id)->count(),
                'virtualware' => Virtualware::query()->where('organization_id', $organization->id)->count(),
                'software' => Software::query()->where('organization_id', $organization->id)->count(),
            ],
            'unassigned_hardware' => Hardware::query()
                ->where('organization_id', $organization->id)
                ->where('status', HardwareStatus::Available)
                ->whereNull('assigned_userware_id')
                ->count(),
            'costs' => [
                'currency' => $primaryCurrency,
                'estimated_monthly' => $estimatedMonthly,
                'estimated_annual' => $estimatedAnnual,
                'upcoming_30_days' => round($upcoming30Days, 2),
                'formatted_monthly' => $this->formatMoney($primaryCurrency, $estimatedMonthly),
                'formatted_annual' => $this->formatMoney($primaryCurrency, $estimatedAnnual),
                'formatted_upcoming_30_days' => $this->formatMoney($primaryCurrency, $upcoming30Days),
                'other_currencies' => $this->otherCurrencyTotals($recurring, $cloudCosts, $primaryCurrency),
            ],
            'monthly_forecast' => $forecast['months'],
            'monthly_forecast_y_axis' => $forecast['y_axis'],
            'monthly_forecast_trend_points' => $forecast['trend_points'],
            'top_costs' => $this->topCosts($recurring, $cloudCosts),
            'upcoming_renewals' => $this->upcomingRenewals($recurring),
            'expiring_licenses' => $this->expiringLicenses($organization),
            'underutilized_seats' => $this->underutilizedSeats($seatLicenses),
        ];
    }

    /**
     * @param  Collection<int, Software>  $recurring
     * @param  Collection<int, CloudTenant>  $cloudCosts
     */
    private function primaryCurrency(Collection $recurring, Collection $cloudCosts): string
    {
        $totals = [];

        foreach ($recurring as $software) {
            $currency = $this->costCurrency($software);
            $totals[$currency] = ($totals[$currency] ?? 0.0) + $this->effectiveMonthlyCost($software);
        }

        foreach ($cloudCosts as $tenant) {
            $currency = $this->currencyCode($tenant->currency);
            $totals[$currency] = ($totals[$currency] ?? 0.0) + ($tenant->monthlyCost() ?? 0.0);
        }

        if ($totals === []) {
            return 'GBP';
        }

        arsort($totals);

        return (string) array_key_first($totals);
    }

    /**
     * @param  Collection<int, Software>  $recurring
     * @param  Collection<int, CloudTenant>  $cloudCosts
     * @return list<array{currency: string, estimated_monthly: float, formatted_monthly: string}>
     */
    private function otherCurrencyTotals(Collection $recurring, Collection $cloudCosts, string $primaryCurrency): array
    {
        $totals = [];
        $primary = strtoupper($primaryCurrency);

        foreach ($recurring as $software) {
            $currency = $this->costCurrency($software);
            if ($currency === $primary) {
                continue;
            }

            $totals[$currency] = ($totals[$currency] ?? 0.0) + $this->effectiveMonthlyCost($software);
        }

        foreach ($cloudCosts as $tenant) {
            $currency = $this->currencyCode($tenant->currency);
            if ($currency === $primary) {
                continue;
            }

            $totals[$currency] = ($totals[$currency] ?? 0.0) + ($tenant->monthlyCost() ?? 0.0);
        }

        return array_values(collect($totals)
            ->map(fn (float $monthly, string $currency): array => [
                'currency' => $currency,
                'estimated_monthly' => round($monthly, 2),
                'formatted_monthly' => $this->formatMoney($currency, round($monthly, 2)),
            ])
            ->sortBy('currency')
            ->all());
    }

    /**
     * @param  Collection<int, Software>  $recurring
     * @param  Collection<int, CloudTenant>  $cloudCosts
     * @return array{
     *     months: list<array{
     *         key: string,
     *         label: string,
     *         mode: string,
     *         actual: float|null,
     *         estimated: float|null,
     *         total: float,
     *         formatted: string,
     *         formatted_actual: string|null,
     *         formatted_estimated: string|null,
     *         percent: float,
     *         actual_segment_percent: float,
     *         estimated_segment_percent: float,
     *         top_actual: list<array{name: string, amount: float, formatted: string}>,
     *         top_estimated: list<array{name: string, amount: float, formatted: string}>
     *     }>,
     *     y_axis: list<array{label: string}>,
     *     trend_points: string
     * }
     */
    private function monthlyForecast(
        Organization $organization,
        Collection $recurring,
        Collection $cloudCosts,
        string $currency,
    ): array {
        $currentMonthStart = now()->startOfMonth();
        $windowStart = $currentMonthStart->copy()->subMonthsNoOverflow(5)->startOfDay();
        $windowEnd = $currentMonthStart->copy()->addMonthsNoOverflow(6)->endOfMonth();
        $months = [];

        for ($offset = -5; $offset <= 6; $offset++) {
            $month = $currentMonthStart->copy()->addMonthsNoOverflow($offset);
            $key = $month->format('Y-m');
            $months[$key] = [
                'key' => $key,
                'label' => $month->format('M'),
                'mode' => $this->forecastModeForMonth($month),
            ];
        }

        $softwareIndex = $this->softwareIndex($recurring);

        $snapshots = CostSnapshot::query()
            ->with('costable')
            ->where('organization_id', $organization->id)
            ->whereBetween('period_start', [
                $windowStart->copy()->subMonthNoOverflow()->toDateString(),
                $windowEnd->toDateString(),
            ])
            ->get()
            ->filter(fn (CostSnapshot $snapshot): bool => $this->currencyCode($snapshot->currency) === strtoupper($currency))
            ->values();

        $billingFallback = round($this->projectedMonthTotal($recurring, $cloudCosts), 2);
        $monthBeforeWindow = $currentMonthStart->copy()->subMonthsNoOverflow(6);
        $baselineBreakdown = $this->pastMonthBreakdown($snapshots, $recurring, $cloudCosts, $softwareIndex, $monthBeforeWindow);
        $baseline = round($baselineBreakdown['total'], 2);
        $baselineLines = $baselineBreakdown['lines'];
        if ($baseline <= 0) {
            $baseline = $billingFallback;
            $baselineLines = $this->pastMonthBreakdown(collect(), $recurring, $cloudCosts, $softwareIndex, $currentMonthStart)['lines'];
        }

        foreach ($months as $key => $month) {
            $monthStart = Carbon::createFromFormat('Y-m-d', $key.'-01')->startOfMonth();
            $actual = null;
            $estimated = null;
            $actualLines = [];
            $estimatedLines = [];

            if ($month['mode'] === 'actual' || $month['mode'] === 'both') {
                $breakdown = $this->pastMonthBreakdown($snapshots, $recurring, $cloudCosts, $softwareIndex, $monthStart);
                $actual = round($breakdown['total'], 2);
                $actualLines = $breakdown['lines'];
            }

            if ($month['mode'] === 'estimated' || $month['mode'] === 'both') {
                $estimated = $baseline;
                $estimatedLines = $baselineLines;
            }

            $total = match ($month['mode']) {
                'actual' => $actual ?? 0.0,
                'estimated' => $estimated ?? 0.0,
                default => max($actual ?? 0.0, $estimated ?? 0.0),
            };

            $months[$key]['actual'] = $actual;
            $months[$key]['estimated'] = $estimated;
            $months[$key]['actual_lines'] = $actualLines;
            $months[$key]['estimated_lines'] = $estimatedLines;
            $months[$key]['total'] = $total;

            if ($month['mode'] === 'actual' && $actual !== null && $actual > 0) {
                $baseline = $actual;
                $baselineLines = $actualLines;
            }
        }

        $dataMax = (float) (collect($months)->max('total') ?: 0.0);
        $axisMax = $this->niceAxisMax($dataMax);

        $mappedMonths = array_values(collect($months)
            ->map(function (array $month) use ($currency, $axisMax): array {
                $total = round((float) $month['total'], 2);
                $actual = $month['actual'];
                $estimated = $month['estimated'];
                $columnPercent = $axisMax > 0 ? round(($total / $axisMax) * 100, 1) : 0.0;

                $actualSegment = 0.0;
                $estimatedSegment = 0.0;

                if ($total > 0) {
                    if ($month['mode'] === 'actual') {
                        $actualSegment = 100.0;
                    } elseif ($month['mode'] === 'estimated') {
                        $estimatedSegment = 100.0;
                    } else {
                        $actualValue = (float) ($actual ?? 0.0);
                        $estimatedValue = (float) ($estimated ?? 0.0);
                        $actualSegment = round(min($actualValue, $total) / $total * 100, 1);
                        $estimatedSegment = round(max($estimatedValue - $actualValue, 0.0) / $total * 100, 1);

                        if ($actualSegment + $estimatedSegment < 100 && $estimatedValue >= $actualValue) {
                            $estimatedSegment = round(100 - $actualSegment, 1);
                        } elseif ($actualSegment + $estimatedSegment < 100) {
                            $actualSegment = 100.0;
                            $estimatedSegment = 0.0;
                        }
                    }
                }

                return [
                    'key' => $month['key'],
                    'label' => $month['label'],
                    'mode' => $month['mode'],
                    'actual' => $actual,
                    'estimated' => $estimated,
                    'total' => $total,
                    'formatted' => $this->formatMoney($currency, $total),
                    'formatted_actual' => $actual === null ? null : $this->formatMoney($currency, $actual),
                    'formatted_estimated' => $estimated === null ? null : $this->formatMoney($currency, $estimated),
                    'percent' => $columnPercent,
                    'actual_segment_percent' => $actualSegment,
                    'estimated_segment_percent' => $estimatedSegment,
                    'top_actual' => $this->topCostLines($month['actual_lines'], $currency),
                    'top_estimated' => $this->topCostLines($month['estimated_lines'], $currency),
                ];
            })
            ->values()
            ->all());

        return [
            'months' => $mappedMonths,
            'y_axis' => [
                ['label' => $this->formatMoney($currency, $axisMax)],
                ['label' => $this->formatMoney($currency, round($axisMax / 2, 2))],
                ['label' => $this->formatMoney($currency, 0.0)],
            ],
            'trend_points' => $this->forecastTrendPoints($mappedMonths),
        ];
    }

    /**
     * SVG polyline points (viewBox 0 0 100 100) through each month column center at its total height.
     *
     * @param  list<array{percent: float}>  $months
     */
    private function forecastTrendPoints(array $months): string
    {
        $count = count($months);

        if ($count === 0) {
            return '';
        }

        $points = [];

        foreach ($months as $index => $month) {
            $x = round((($index + 0.5) / $count) * 100, 2);
            $y = round(100 - (float) $month['percent'], 2);
            $points[] = $x.','.$y;
        }

        return implode(' ', $points);
    }

    /**
     * Cloud provider invoices often finalize a few days after month end.
     * Keep the prior month provisional through the 5th of the following month.
     */
    private function forecastModeForMonth(CarbonInterface $monthStart): string
    {
        $monthStart = $monthStart->copy()->startOfMonth();
        $billingFinalizedAfter = $monthStart->copy()->addMonthNoOverflow()->day(5)->endOfDay();

        if (now()->gt($billingFinalizedAfter)) {
            return 'actual';
        }

        if ($monthStart->gt(now()->startOfMonth())) {
            return 'estimated';
        }

        return 'both';
    }

    /**
     * One snapshot per asset for the month. Daily syncs rewrite the current
     * period with a new end date, so summing every row would multiply the estimate.
     *
     * @param  Collection<int, CostSnapshot>  $snapshots
     * @param  Collection<int, Software>  $recurring
     * @param  Collection<int, CloudTenant>  $cloudCosts
     * @param  Collection<int, Software>  $softwareIndex
     * @return array{total: float, lines: list<array{name: string, amount: float}>}
     */
    private function pastMonthBreakdown(
        Collection $snapshots,
        Collection $recurring,
        Collection $cloudCosts,
        Collection $softwareIndex,
        CarbonInterface $monthStart,
    ): array {
        $monthSnapshots = $this->latestSnapshotsForMonth($snapshots, $monthStart->format('Y-m'));

        $total = 0.0;
        $lines = [];
        $coveredRoots = [];
        $coveredCloud = [];
        /** @var array<int, array{parent: float|null, children: float}> $rootAmounts */
        $rootAmounts = [];

        foreach ($monthSnapshots as $snapshot) {
            if ($snapshot->costable_type === CloudTenant::class) {
                $amount = (float) $snapshot->amount;
                $total += $amount;
                $coveredCloud[$snapshot->costable_id] = true;
                $lines[] = [
                    'name' => $this->cloudSnapshotName($snapshot, $cloudCosts),
                    'amount' => $amount,
                ];

                continue;
            }

            if ($snapshot->costable_type !== Software::class) {
                $amount = (float) $snapshot->amount;
                $total += $amount;
                $lines[] = [
                    'name' => $this->costableName($snapshot) ?? __('Other'),
                    'amount' => $amount,
                ];

                continue;
            }

            $software = $softwareIndex->get($snapshot->costable_id);
            if ($software === null) {
                continue;
            }

            $rootId = $software->parent_software_id ?? $software->id;
            $rootAmounts[$rootId] ??= ['parent' => null, 'children' => 0.0];

            if ($software->parent_software_id === null) {
                $rootAmounts[$rootId]['parent'] = ($rootAmounts[$rootId]['parent'] ?? 0.0) + (float) $snapshot->amount;
            } else {
                $rootAmounts[$rootId]['children'] += (float) $snapshot->amount;
            }
        }

        foreach ($rootAmounts as $rootId => $parts) {
            $parentAmount = $parts['parent'];
            $amount = ($parentAmount !== null && $parentAmount > 0)
                ? $parentAmount
                : $parts['children'];
            $total += $amount;
            $coveredRoots[$rootId] = true;
            $root = $softwareIndex->get($rootId);
            $lines[] = [
                'name' => $root instanceof Software ? $root->name : __('Software'),
                'amount' => $amount,
            ];
        }

        foreach ($recurring as $software) {
            if (isset($coveredRoots[$software->id])) {
                continue;
            }

            $amount = $this->effectiveMonthlyCost($software);
            $total += $amount;
            $lines[] = [
                'name' => $software->name,
                'amount' => $amount,
            ];
        }

        foreach ($cloudCosts as $tenant) {
            if (isset($coveredCloud[$tenant->id])) {
                continue;
            }

            $amount = $tenant->monthlyCost() ?? 0.0;
            $total += $amount;
            $lines[] = [
                'name' => $tenant->name,
                'amount' => $amount,
            ];
        }

        return [
            'total' => $total,
            'lines' => $lines,
        ];
    }

    /**
     * @param  Collection<int, CostSnapshot>  $snapshots
     * @return Collection<int, CostSnapshot>
     */
    private function latestSnapshotsForMonth(Collection $snapshots, string $monthKey): Collection
    {
        return $snapshots
            ->filter(fn (CostSnapshot $snapshot): bool => $snapshot->period_start->format('Y-m') === $monthKey)
            ->groupBy(fn (CostSnapshot $snapshot): string => $snapshot->costable_type.'#'.$snapshot->costable_id)
            ->map(function (Collection $group): CostSnapshot {
                /** @var CostSnapshot $latest */
                $latest = $group->sortBy(fn (CostSnapshot $snapshot): string => $snapshot->period_end->toDateString()
                    .'|'.($snapshot->synced_at?->utc()->format('Y-m-d H:i:s') ?? '')
                    .'|'.str_pad((string) $snapshot->id, 12, '0', STR_PAD_LEFT))->last();

                return $latest;
            })
            ->values();
    }

    /**
     * @param  Collection<int, CloudTenant>  $cloudCosts
     */
    private function cloudSnapshotName(CostSnapshot $snapshot, Collection $cloudCosts): string
    {
        $tenant = $cloudCosts->firstWhere('id', $snapshot->costable_id);
        if ($tenant instanceof CloudTenant) {
            return $tenant->name;
        }

        return $this->costableName($snapshot) ?? __('Cloud');
    }

    private function costableName(CostSnapshot $snapshot): ?string
    {
        $costable = $snapshot->costable;

        if (! $costable instanceof Software && ! $costable instanceof CloudTenant) {
            return null;
        }

        return $costable->name !== '' ? $costable->name : null;
    }

    /**
     * @param  list<array{name: string, amount: float}>  $lines
     * @return list<array{name: string, amount: float, formatted: string}>
     */
    private function topCostLines(array $lines, string $currency): array
    {
        $lines = array_values(array_filter(
            $lines,
            fn (array $line): bool => $line['amount'] > 0 && $line['name'] !== '',
        ));

        usort($lines, fn (array $left, array $right): int => $right['amount'] <=> $left['amount']);

        return array_map(
            function (array $line) use ($currency): array {
                $amount = round($line['amount'], 2);

                return [
                    'name' => $line['name'],
                    'amount' => $amount,
                    'formatted' => $this->formatMoney($currency, $amount),
                ];
            },
            array_slice($lines, 0, 3),
        );
    }

    /**
     * @param  Collection<int, Software>  $recurring
     * @param  Collection<int, CloudTenant>  $cloudCosts
     */
    private function projectedMonthTotal(Collection $recurring, Collection $cloudCosts): float
    {
        $softwareTotal = $recurring->sum(fn (Software $software): float => $this->effectiveMonthlyCost($software));
        $cloudTotal = $cloudCosts->sum(fn (CloudTenant $tenant): float => $tenant->monthlyCost() ?? 0.0);

        return (float) $softwareTotal + (float) $cloudTotal;
    }

    /**
     * @return list<CarbonInterface>
     */
    private function billingDates(Software $software, CarbonInterface $rangeStart, CarbonInterface $rangeEnd): array
    {
        if ($software->billing_interval === null || $software->billing_amount === null) {
            return [];
        }

        $cursor = $software->next_billing_at?->copy()->startOfDay()
            ?? $rangeStart->copy()->startOfDay();

        $months = $software->billing_interval->monthsPerPeriod();
        $dates = [];
        $guard = 0;

        while ($cursor->lt($rangeStart) && $guard < 48) {
            $cursor = $cursor->addMonthsNoOverflow($months);
            $guard++;
        }

        $guard = 0;

        while ($cursor->lte($rangeEnd) && $guard < 48) {
            if ($cursor->gte($rangeStart)) {
                $dates[] = $cursor->copy();
            }

            $cursor = $cursor->addMonthsNoOverflow($months);
            $guard++;
        }

        return $dates;
    }

    /**
     * @param  Collection<int, Software>  $recurring
     * @param  Collection<int, CloudTenant>  $cloudCosts
     */
    private function upcomingBillingTotal(Collection $recurring, Collection $cloudCosts, int $days): float
    {
        $start = now()->startOfDay();
        $end = now()->addDays($days)->endOfDay();

        $softwareTotal = $recurring->sum(function (Software $software) use ($start, $end): float {
            if ($software->billing_amount !== null && $software->billing_interval !== null) {
                return collect($this->billingDates($software, $start, $end))
                    ->sum(fn (): float => (float) $software->billing_amount);
            }

            return $this->effectiveMonthlyCost($software);
        });

        $cloudTotal = $cloudCosts->sum(fn (CloudTenant $tenant): float => $tenant->monthlyCost() ?? 0.0);

        return round((float) $softwareTotal + (float) $cloudTotal, 2);
    }

    /**
     * @param  Collection<int, Software>  $recurring
     * @param  Collection<int, CloudTenant>  $cloudCosts
     * @return list<array{id: int, type: string, name: string, vendor: string|null, monthly: float, formatted: string, percent: float}>
     */
    private function topCosts(Collection $recurring, Collection $cloudCosts): array
    {
        $softwareRows = $recurring
            ->map(function (Software $software): ?array {
                $monthly = $this->effectiveMonthlyCost($software);

                if ($monthly <= 0) {
                    return null;
                }

                return [
                    'id' => $software->id,
                    'type' => 'software',
                    'name' => $software->name,
                    'vendor' => $software->vendor,
                    'monthly' => $monthly,
                    'formatted' => $this->formatMoney($this->costCurrency($software), $monthly),
                ];
            })
            ->filter();

        $cloudRows = $cloudCosts
            ->map(function (CloudTenant $tenant): ?array {
                $monthly = $tenant->monthlyCost();

                if ($monthly === null || $monthly <= 0) {
                    return null;
                }

                return [
                    'id' => $tenant->id,
                    'type' => 'cloud_tenant',
                    'name' => $tenant->name,
                    'vendor' => $tenant->provider->label(),
                    'monthly' => $monthly,
                    'formatted' => $this->formatMoney($this->currencyCode($tenant->currency), $monthly),
                ];
            })
            ->filter();

        $rows = $softwareRows
            ->concat($cloudRows)
            ->sortByDesc('monthly')
            ->take(5)
            ->values();

        $max = $rows->max('monthly') ?: 0.0;

        return array_values($rows
            ->map(function (array $row) use ($max): array {
                $row['percent'] = $max > 0 ? round(($row['monthly'] / $max) * 100, 1) : 0.0;

                return $row;
            })
            ->all());
    }

    /**
     * Prefer child products when they have their own renewal dates so suites can renew on different days.
     *
     * @param  Collection<int, Software>  $recurring
     * @return list<array{id: int, name: string, amount: float, formatted_amount: string, currency: string, next_billing_at: string}>
     */
    private function upcomingRenewals(Collection $recurring): array
    {
        $limit = now()->addDays(60)->endOfDay();

        $candidates = $recurring
            ->flatMap(function (Software $software): Collection {
                $childrenWithRenewals = $software->childSoftwares
                    ->filter(fn (Software $child): bool => $child->status === SoftwareStatus::Active
                        && $child->next_billing_at !== null);

                if ($childrenWithRenewals->isNotEmpty()) {
                    return $childrenWithRenewals->values();
                }

                return collect([$software]);
            })
            ->values();

        return array_values($candidates
            ->filter(fn (Software $software): bool => $software->next_billing_at !== null
                && $software->next_billing_at->lte($limit))
            ->sortBy('next_billing_at')
            ->take(6)
            ->map(function (Software $software): array {
                $currency = $this->currencyCode($software->currency);
                $amount = $software->billing_amount !== null
                    ? (float) $software->billing_amount
                    : $this->effectiveMonthlyCost($software);

                return [
                    'id' => $software->id,
                    'name' => $software->name,
                    'amount' => $amount,
                    'formatted_amount' => $this->formatMoney($currency, $amount),
                    'currency' => $currency,
                    'next_billing_at' => $software->next_billing_at->toDateString(),
                ];
            })
            ->values()
            ->all());
    }

    /**
     * @return list<array{id: int, name: string, expires_at: string}>
     */
    private function expiringLicenses(Organization $organization): array
    {
        return array_values(Software::query()
            ->where('organization_id', $organization->id)
            ->where('status', SoftwareStatus::Active)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now()->toDateString(), now()->addDays(60)->toDateString()])
            ->orderBy('expires_at')
            ->limit(6)
            ->get(['id', 'name', 'expires_at'])
            ->map(fn (Software $software): array => [
                'id' => $software->id,
                'name' => $software->name,
                'expires_at' => $software->expires_at->toDateString(),
            ])
            ->all());
    }

    /**
     * @param  Collection<int, Software>  $seatLicenses
     * @return list<array{id: int, name: string, used: int, total: int, unused: int}>
     */
    private function underutilizedSeats(Collection $seatLicenses): array
    {
        return array_values($seatLicenses
            ->filter(function (Software $software): bool {
                $total = (int) $software->total_seats;
                $used = (int) $software->assignments_count;

                return $total > 0 && ($total - $used) >= max(2, (int) ceil($total * 0.25));
            })
            ->sortByDesc(fn (Software $software): int => (int) $software->total_seats - (int) $software->assignments_count)
            ->take(5)
            ->map(fn (Software $software): array => [
                'id' => $software->id,
                'name' => $software->name,
                'used' => (int) $software->assignments_count,
                'total' => (int) $software->total_seats,
                'unused' => (int) $software->total_seats - (int) $software->assignments_count,
            ])
            ->values()
            ->all());
    }

    /**
     * Prefer the suite/parent monthly amount; otherwise sum active child product costs.
     */
    private function effectiveMonthlyCost(Software $software): float
    {
        $own = $software->monthlyCost();
        if ($own !== null && $own > 0) {
            return $own;
        }

        $childTotal = $software->childSoftwares
            ->filter(fn (Software $child): bool => $child->status === SoftwareStatus::Active)
            ->sum(fn (Software $child): float => $child->monthlyCost() ?? 0.0);

        if ($childTotal > 0) {
            return round((float) $childTotal, 2);
        }

        return $own ?? 0.0;
    }

    /**
     * Currency for a suite cost row: own billing currency, else the first child product with cost.
     */
    private function costCurrency(Software $software): string
    {
        $own = $software->monthlyCost();
        if ($own !== null && $own > 0) {
            return $this->currencyCode($software->currency);
        }

        $child = $software->childSoftwares->first(
            fn (Software $child): bool => $child->status === SoftwareStatus::Active
                && ($child->monthlyCost() ?? 0.0) > 0,
        );

        if ($child !== null) {
            return $this->currencyCode($child->currency);
        }

        return $this->currencyCode($software->currency);
    }

    /**
     * @param  Collection<int, Software>  $recurring
     * @return Collection<int, Software>
     */
    private function softwareIndex(Collection $recurring): Collection
    {
        $index = collect();

        foreach ($recurring as $software) {
            $index->put($software->id, $software);

            foreach ($software->childSoftwares as $child) {
                $index->put($child->id, $child);
            }
        }

        return $index;
    }

    private function niceAxisMax(float $max): float
    {
        if ($max <= 0) {
            return 0.0;
        }

        $exponent = (int) floor(log10($max));
        $magnitude = 10 ** $exponent;
        $fraction = $max / $magnitude;

        $niceFraction = match (true) {
            $fraction <= 1 => 1.0,
            $fraction <= 2 => 2.0,
            $fraction <= 5 => 5.0,
            default => 10.0,
        };

        return round($niceFraction * $magnitude, 2);
    }

    private function currencyCode(?string $currency): string
    {
        return strtoupper($currency ?: 'GBP');
    }

    private function formatMoney(string $currency, float $amount): string
    {
        return strtoupper($currency).' '.number_format($amount, 2);
    }
}
