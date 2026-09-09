<?php

namespace App\Support;

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
     *         estimated_segment_percent: float
     *     }>,
     *     top_costs: list<array{id: int, type: string, name: string, vendor: string|null, monthly: float, formatted: string, percent: float}>,
     *     upcoming_renewals: list<array{id: int, name: string, amount: float, formatted_amount: string, currency: string, next_billing_at: string}>,
     *     expiring_licenses: list<array{id: int, name: string, expires_at: string}>,
     *     underutilized_seats: list<array{id: int, name: string, used: int, total: int, unused: int}>
     * }
     */
    public function for(Organization $organization): array
    {
        $recurring = Software::query()
            ->where('organization_id', $organization->id)
            ->where('is_recurring', true)
            ->where('status', SoftwareStatus::Active)
            ->whereNotNull('billing_amount')
            ->whereNotNull('billing_interval')
            ->orderBy('name')
            ->get();

        $cloudCosts = CloudTenant::query()
            ->where('organization_id', $organization->id)
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
            ->filter(fn (Software $software): bool => $this->currencyCode($software->currency) === $primaryCurrency)
            ->values();
        $primaryCloudCosts = $cloudCosts
            ->filter(fn (CloudTenant $tenant): bool => $this->currencyCode($tenant->currency) === $primaryCurrency)
            ->values();

        $softwareMonthly = round($primaryRecurring->sum(fn (Software $software): float => $software->monthlyCost() ?? 0.0), 2);
        $cloudMonthly = round($primaryCloudCosts->sum(fn (CloudTenant $tenant): float => $tenant->monthlyCost() ?? 0.0), 2);
        $estimatedMonthly = round($softwareMonthly + $cloudMonthly, 2);
        $estimatedAnnual = round($estimatedMonthly * 12, 2);
        $monthlyForecast = $this->monthlyForecast($organization, $primaryRecurring, $primaryCloudCosts, $primaryCurrency);
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
            'monthly_forecast' => $monthlyForecast,
            'top_costs' => $this->topCosts($primaryRecurring, $primaryCloudCosts),
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
        $currencies = $recurring
            ->map(fn (Software $software): string => $this->currencyCode($software->currency))
            ->concat($cloudCosts->map(fn (CloudTenant $tenant): string => $this->currencyCode($tenant->currency)));

        if ($currencies->isEmpty()) {
            return 'GBP';
        }

        return (string) $currencies
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();
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
            $currency = $this->currencyCode($software->currency);
            if ($currency === $primary) {
                continue;
            }

            $totals[$currency] = ($totals[$currency] ?? 0.0) + ($software->monthlyCost() ?? 0.0);
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
     * @return list<array{
     *     key: string,
     *     label: string,
     *     mode: string,
     *     actual: float|null,
     *     estimated: float|null,
     *     total: float,
     *     formatted: string,
     *     formatted_actual: string|null,
     *     formatted_estimated: string|null,
     *     percent: float,
     *     actual_segment_percent: float,
     *     estimated_segment_percent: float
     * }>
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

        $snapshots = CostSnapshot::query()
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
        $baseline = round($this->pastMonthTotal($snapshots, $recurring, $cloudCosts, $monthBeforeWindow), 2);
        if ($baseline <= 0) {
            $baseline = $billingFallback;
        }

        foreach ($months as $key => $month) {
            $monthStart = Carbon::createFromFormat('Y-m-d', $key.'-01')->startOfMonth();
            $actual = null;
            $estimated = null;

            if ($month['mode'] === 'actual' || $month['mode'] === 'both') {
                $actual = round($this->pastMonthTotal($snapshots, $recurring, $cloudCosts, $monthStart), 2);
            }

            if ($month['mode'] === 'estimated' || $month['mode'] === 'both') {
                $estimated = $baseline;
            }

            $total = match ($month['mode']) {
                'actual' => $actual ?? 0.0,
                'estimated' => $estimated ?? 0.0,
                default => max($actual ?? 0.0, $estimated ?? 0.0),
            };

            $months[$key]['actual'] = $actual;
            $months[$key]['estimated'] = $estimated;
            $months[$key]['total'] = $total;

            $baseline = match ($month['mode']) {
                'actual' => ($actual !== null && $actual > 0) ? $actual : $baseline,
                'both', 'estimated' => ($estimated !== null && $estimated > 0) ? $estimated : $baseline,
                default => $baseline,
            };
        }

        $max = collect($months)->max('total') ?: 0.0;

        return array_values(collect($months)
            ->map(function (array $month) use ($currency, $max): array {
                $total = round((float) $month['total'], 2);
                $actual = $month['actual'];
                $estimated = $month['estimated'];
                $columnPercent = $max > 0 ? round(($total / $max) * 100, 1) : 0.0;

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
                ];
            })
            ->values()
            ->all());
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
     * @param  Collection<int, CostSnapshot>  $snapshots
     * @param  Collection<int, Software>  $recurring
     * @param  Collection<int, CloudTenant>  $cloudCosts
     */
    private function pastMonthTotal(
        Collection $snapshots,
        Collection $recurring,
        Collection $cloudCosts,
        CarbonInterface $monthStart,
    ): float {
        $monthKey = $monthStart->format('Y-m');
        $monthSnapshots = $snapshots->filter(
            fn (CostSnapshot $snapshot): bool => $snapshot->period_start->format('Y-m') === $monthKey,
        );

        $total = 0.0;
        $covered = [];

        foreach ($monthSnapshots as $snapshot) {
            $total += (float) $snapshot->amount;
            $covered[$snapshot->costable_type.':'.$snapshot->costable_id] = true;
        }

        foreach ($recurring as $software) {
            if (isset($covered[Software::class.':'.$software->id])) {
                continue;
            }

            $total += $software->monthlyCost() ?? 0.0;
        }

        foreach ($cloudCosts as $tenant) {
            if (isset($covered[CloudTenant::class.':'.$tenant->id])) {
                continue;
            }

            $total += $tenant->monthlyCost() ?? 0.0;
        }

        return $total;
    }

    /**
     * @param  Collection<int, Software>  $recurring
     * @param  Collection<int, CloudTenant>  $cloudCosts
     */
    private function projectedMonthTotal(Collection $recurring, Collection $cloudCosts): float
    {
        $softwareTotal = $recurring->sum(fn (Software $software): float => $software->monthlyCost() ?? 0.0);
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
            return collect($this->billingDates($software, $start, $end))
                ->sum(fn (): float => (float) $software->billing_amount);
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
                $monthly = $software->monthlyCost();

                if ($monthly === null || $monthly <= 0) {
                    return null;
                }

                return [
                    'id' => $software->id,
                    'type' => 'software',
                    'name' => $software->name,
                    'vendor' => $software->vendor,
                    'monthly' => $monthly,
                    'formatted' => $this->formatMoney($this->currencyCode($software->currency), $monthly),
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
            ->take(8)
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
     * @param  Collection<int, Software>  $recurring
     * @return list<array{id: int, name: string, amount: float, formatted_amount: string, currency: string, next_billing_at: string}>
     */
    private function upcomingRenewals(Collection $recurring): array
    {
        $limit = now()->addDays(60)->endOfDay();

        return array_values($recurring
            ->filter(fn (Software $software): bool => $software->next_billing_at !== null
                && $software->next_billing_at->lte($limit))
            ->sortBy('next_billing_at')
            ->take(6)
            ->map(function (Software $software): array {
                $currency = $this->currencyCode($software->currency);
                $amount = (float) $software->billing_amount;

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

    private function currencyCode(?string $currency): string
    {
        return strtoupper($currency ?: 'GBP');
    }

    private function formatMoney(string $currency, float $amount): string
    {
        return strtoupper($currency).' '.number_format($amount, 2);
    }
}
