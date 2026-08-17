<?php

namespace App\Support;

use App\Enums\HardwareStatus;
use App\Enums\SoftwareStatus;
use App\Models\CloudTenant;
use App\Models\Hardware;
use App\Models\Organization;
use App\Models\Software;
use App\Models\Userware;
use App\Models\Virtualware;
use Carbon\CarbonInterface;
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
     *     monthly_forecast: list<array{key: string, label: string, total: float, formatted: string, percent: float}>,
     *     top_software_costs: list<array{id: int, name: string, vendor: string|null, monthly: float, formatted: string, percent: float}>,
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

        $seatLicenses = Software::query()
            ->withCount('assignments')
            ->where('organization_id', $organization->id)
            ->where('status', SoftwareStatus::Active)
            ->whereNotNull('total_seats')
            ->get();

        $primaryCurrency = $this->primaryCurrency($recurring);
        $primaryRecurring = $recurring->where('currency', $primaryCurrency)->values();

        $estimatedMonthly = round($primaryRecurring->sum(fn (Software $software): float => $software->monthlyCost() ?? 0.0), 2);
        $estimatedAnnual = round($estimatedMonthly * 12, 2);
        $monthlyForecast = $this->monthlyForecast($primaryRecurring, $primaryCurrency);
        $upcoming30Days = $this->upcomingBillingTotal($primaryRecurring, 30);

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
                'other_currencies' => $this->otherCurrencyTotals($recurring, $primaryCurrency),
            ],
            'monthly_forecast' => $monthlyForecast,
            'top_software_costs' => $this->topSoftwareCosts($primaryRecurring),
            'upcoming_renewals' => $this->upcomingRenewals($recurring),
            'expiring_licenses' => $this->expiringLicenses($organization),
            'underutilized_seats' => $this->underutilizedSeats($seatLicenses),
        ];
    }

    /**
     * @param  Collection<int, Software>  $recurring
     */
    private function primaryCurrency(Collection $recurring): string
    {
        if ($recurring->isEmpty()) {
            return 'GBP';
        }

        return (string) $recurring
            ->groupBy(fn (Software $software): string => strtoupper($software->currency ?: 'GBP'))
            ->sortByDesc(fn (Collection $group): int => $group->count())
            ->keys()
            ->first();
    }

    /**
     * @param  Collection<int, Software>  $recurring
     * @return list<array{currency: string, estimated_monthly: float, formatted_monthly: string}>
     */
    private function otherCurrencyTotals(Collection $recurring, string $primaryCurrency): array
    {
        return $recurring
            ->groupBy(fn (Software $software): string => strtoupper($software->currency ?: 'GBP'))
            ->reject(fn (Collection $group, string $currency): bool => $currency === $primaryCurrency)
            ->map(function (Collection $group, string $currency): array {
                $monthly = round($group->sum(fn (Software $software): float => $software->monthlyCost() ?? 0.0), 2);

                return [
                    'currency' => $currency,
                    'estimated_monthly' => $monthly,
                    'formatted_monthly' => $this->formatMoney($currency, $monthly),
                ];
            })
            ->sortBy('currency')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Software>  $recurring
     * @return list<array{key: string, label: string, total: float, formatted: string, percent: float}>
     */
    private function monthlyForecast(Collection $recurring, string $currency): array
    {
        $start = now()->startOfMonth();
        $end = $start->copy()->addMonthsNoOverflow(11)->endOfMonth();
        $months = [];

        for ($offset = 0; $offset < 12; $offset++) {
            $month = $start->copy()->addMonthsNoOverflow($offset);
            $key = $month->format('Y-m');
            $months[$key] = [
                'key' => $key,
                'label' => $month->format('M'),
                'total' => 0.0,
            ];
        }

        foreach ($recurring as $software) {
            foreach ($this->billingDates($software, $start, $end) as $date) {
                $key = $date->format('Y-m');

                if (! isset($months[$key])) {
                    continue;
                }

                $months[$key]['total'] += (float) $software->billing_amount;
            }
        }

        $max = collect($months)->max('total') ?: 0.0;

        return collect($months)
            ->map(function (array $month) use ($currency, $max): array {
                $total = round($month['total'], 2);

                return [
                    'key' => $month['key'],
                    'label' => $month['label'],
                    'total' => $total,
                    'formatted' => $this->formatMoney($currency, $total),
                    'percent' => $max > 0 ? round(($total / $max) * 100, 1) : 0.0,
                ];
            })
            ->values()
            ->all();
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
     */
    private function upcomingBillingTotal(Collection $recurring, int $days): float
    {
        $start = now()->startOfDay();
        $end = now()->addDays($days)->endOfDay();

        return round($recurring->sum(function (Software $software) use ($start, $end): float {
            return collect($this->billingDates($software, $start, $end))
                ->sum(fn (): float => (float) $software->billing_amount);
        }), 2);
    }

    /**
     * @param  Collection<int, Software>  $recurring
     * @return list<array{id: int, name: string, vendor: string|null, monthly: float, formatted: string, percent: float}>
     */
    private function topSoftwareCosts(Collection $recurring): array
    {
        $rows = $recurring
            ->map(function (Software $software): ?array {
                $monthly = $software->monthlyCost();

                if ($monthly === null || $monthly <= 0) {
                    return null;
                }

                return [
                    'id' => $software->id,
                    'name' => $software->name,
                    'vendor' => $software->vendor,
                    'monthly' => $monthly,
                    'formatted' => $this->formatMoney(strtoupper($software->currency ?: 'GBP'), $monthly),
                ];
            })
            ->filter()
            ->sortByDesc('monthly')
            ->take(8)
            ->values();

        $max = $rows->max('monthly') ?: 0.0;

        return $rows
            ->map(function (array $row) use ($max): array {
                $row['percent'] = $max > 0 ? round(($row['monthly'] / $max) * 100, 1) : 0.0;

                return $row;
            })
            ->all();
    }

    /**
     * @param  Collection<int, Software>  $recurring
     * @return list<array{id: int, name: string, amount: float, formatted_amount: string, currency: string, next_billing_at: string}>
     */
    private function upcomingRenewals(Collection $recurring): array
    {
        $limit = now()->addDays(60)->endOfDay();

        return $recurring
            ->filter(fn (Software $software): bool => $software->next_billing_at !== null
                && $software->next_billing_at->lte($limit))
            ->sortBy('next_billing_at')
            ->take(6)
            ->map(function (Software $software): array {
                $currency = strtoupper($software->currency ?: 'GBP');
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
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, expires_at: string}>
     */
    private function expiringLicenses(Organization $organization): array
    {
        return Software::query()
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
            ->all();
    }

    /**
     * @param  Collection<int, Software>  $seatLicenses
     * @return list<array{id: int, name: string, used: int, total: int, unused: int}>
     */
    private function underutilizedSeats(Collection $seatLicenses): array
    {
        return $seatLicenses
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
            ->all();
    }

    private function formatMoney(string $currency, float $amount): string
    {
        return strtoupper($currency).' '.number_format($amount, 2);
    }
}
