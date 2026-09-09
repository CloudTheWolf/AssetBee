<?php

namespace App\Support;

use App\Enums\CloudTenantStatus;
use App\Enums\SoftwareStatus;
use App\Models\CloudTenant;
use App\Models\Organization;
use App\Models\Software;
use Illuminate\Support\Collection;

class OrganizationCostBreakdownReport
{
    /**
     * @return array{
     *     currency: string,
     *     estimated_monthly: float,
     *     formatted_monthly: string,
     *     other_currencies: list<array{currency: string, estimated_monthly: float, formatted_monthly: string}>,
     *     software_total: float,
     *     formatted_software_total: string,
     *     cloud_total: float,
     *     formatted_cloud_total: string,
     *     line_item_count: int,
     *     software: list<array{
     *         id: int,
     *         name: string,
     *         vendor: string|null,
     *         url: string,
     *         monthly: float,
     *         formatted: string,
     *         interval: string|null,
     *         currency: string,
     *         children: list<array{
     *             id: int,
     *             name: string,
     *             vendor: string|null,
     *             url: string,
     *             monthly: float,
     *             formatted: string,
     *             interval: string|null,
     *             currency: string
     *         }>
     *     }>,
     *     cloud: list<array{
     *         id: int,
     *         name: string,
     *         vendor: string|null,
     *         url: string,
     *         monthly: float,
     *         formatted: string,
     *         interval: string|null,
     *         currency: string
     *     }>
     * }
     */
    public function for(Organization $organization): array
    {
        $softwareRoots = $this->softwareRoots($organization);
        $cloudTenants = $this->cloudTenants($organization);

        $primaryCurrency = $this->primaryCurrency($softwareRoots, $cloudTenants);

        $softwareRows = $softwareRoots
            ->map(fn (Software $software): ?array => $this->presentSoftware($software))
            ->filter()
            ->values();

        $cloudRows = $cloudTenants
            ->map(fn (CloudTenant $tenant): ?array => $this->presentCloud($tenant))
            ->filter()
            ->values();

        $softwareTotal = round($softwareRows
            ->filter(fn (array $row): bool => $row['currency'] === $primaryCurrency)
            ->sum('monthly'), 2);

        $cloudTotal = round($cloudRows
            ->filter(fn (array $row): bool => $row['currency'] === $primaryCurrency)
            ->sum('monthly'), 2);

        $estimatedMonthly = round($softwareTotal + $cloudTotal, 2);

        return [
            'currency' => $primaryCurrency,
            'estimated_monthly' => $estimatedMonthly,
            'formatted_monthly' => $this->formatMoney($primaryCurrency, $estimatedMonthly),
            'other_currencies' => $this->otherCurrencyTotals($softwareRows, $cloudRows, $primaryCurrency),
            'software_total' => $softwareTotal,
            'formatted_software_total' => $this->formatMoney($primaryCurrency, $softwareTotal),
            'cloud_total' => $cloudTotal,
            'formatted_cloud_total' => $this->formatMoney($primaryCurrency, $cloudTotal),
            'line_item_count' => $softwareRows->count() + $cloudRows->count(),
            'software' => $softwareRows->all(),
            'cloud' => $cloudRows->all(),
        ];
    }

    /**
     * @return list<string>
     */
    public function pdfLines(Organization $organization): array
    {
        $report = $this->for($organization);

        $lines = [
            __('Estimated monthly spend by software and cloud, with sub-products nested under their parent.'),
            '',
            __('Organization').': '.$organization->name,
            __('Generated').': '.now()->timezone((string) config('app.timezone'))->toDayDateTimeString(),
            __('Estimated monthly').': '.$report['formatted_monthly'],
            '',
        ];

        foreach ($report['other_currencies'] as $other) {
            $lines[] = __('Also :amount / mo', ['amount' => $other['formatted_monthly']]);
        }

        if ($report['other_currencies'] !== []) {
            $lines[] = '';
        }

        $lines[] = __('Software').' ('.$report['formatted_software_total'].')';
        $lines[] = '';

        if ($report['software'] === []) {
            $lines[] = __('No software costs.');
            $lines[] = '';
        } else {
            foreach ($report['software'] as $parent) {
                $lines[] = $parent['name'].' | '.($parent['vendor'] ?? '—').' | '.$parent['formatted']
                    .($parent['interval'] ? ' ('.$parent['interval'].')' : '');

                foreach ($parent['children'] as $child) {
                    $lines[] = '  - '.$child['name'].' | '.($child['vendor'] ?? '—').' | '.$child['formatted']
                        .($child['interval'] ? ' ('.$child['interval'].')' : '');
                }
            }

            $lines[] = '';
        }

        $lines[] = __('Cloud').' ('.$report['formatted_cloud_total'].')';
        $lines[] = '';

        if ($report['cloud'] === []) {
            $lines[] = __('No cloud costs.');
        } else {
            foreach ($report['cloud'] as $tenant) {
                $lines[] = $tenant['name'].' | '.($tenant['vendor'] ?? '—').' | '.$tenant['formatted']
                    .($tenant['interval'] ? ' ('.$tenant['interval'].')' : '');
            }
        }

        return $lines;
    }

    /**
     * @return Collection<int, Software>
     */
    private function softwareRoots(Organization $organization): Collection
    {
        return Software::query()
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
    }

    /**
     * @return Collection<int, CloudTenant>
     */
    private function cloudTenants(Organization $organization): Collection
    {
        return CloudTenant::query()
            ->where('organization_id', $organization->id)
            ->where('status', CloudTenantStatus::Active)
            ->whereNotNull('billing_amount')
            ->whereNotNull('billing_interval')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     vendor: string|null,
     *     url: string,
     *     monthly: float,
     *     formatted: string,
     *     interval: string|null,
     *     currency: string,
     *     children: list<array{
     *         id: int,
     *         name: string,
     *         vendor: string|null,
     *         url: string,
     *         monthly: float,
     *         formatted: string,
     *         interval: string|null,
     *         currency: string
     *     }>
     * }|null
     */
    private function presentSoftware(Software $software): ?array
    {
        $monthly = $this->effectiveMonthlyCost($software);

        if ($monthly <= 0) {
            return null;
        }

        $currency = $this->costCurrency($software);
        $children = $software->childSoftwares
            ->filter(fn (Software $child): bool => $child->status === SoftwareStatus::Active)
            ->map(function (Software $child): ?array {
                $childMonthly = $child->monthlyCost() ?? 0.0;

                if ($childMonthly <= 0) {
                    return null;
                }

                $childCurrency = $this->currencyCode($child->currency);

                return [
                    'id' => $child->id,
                    'name' => $child->name,
                    'vendor' => $child->vendor,
                    'url' => route('assets.software.show', $child),
                    'monthly' => $childMonthly,
                    'formatted' => $this->formatMoney($childCurrency, $childMonthly),
                    'interval' => $child->billing_interval?->label(),
                    'currency' => $childCurrency,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return [
            'id' => $software->id,
            'name' => $software->name,
            'vendor' => $software->vendor,
            'url' => route('assets.software.show', $software),
            'monthly' => $monthly,
            'formatted' => $this->formatMoney($currency, $monthly),
            'interval' => $software->billing_interval?->label(),
            'currency' => $currency,
            'children' => $children,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     vendor: string|null,
     *     url: string,
     *     monthly: float,
     *     formatted: string,
     *     interval: string|null,
     *     currency: string
     * }|null
     */
    private function presentCloud(CloudTenant $tenant): ?array
    {
        $monthly = $tenant->monthlyCost();

        if ($monthly === null || $monthly <= 0) {
            return null;
        }

        $currency = $this->currencyCode($tenant->currency);

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'vendor' => $tenant->provider->label(),
            'url' => route('assets.cloud-tenants.show', $tenant),
            'monthly' => $monthly,
            'formatted' => $this->formatMoney($currency, $monthly),
            'interval' => $tenant->billing_interval?->label(),
            'currency' => $currency,
        ];
    }

    /**
     * @param  Collection<int, Software>  $softwareRoots
     * @param  Collection<int, CloudTenant>  $cloudTenants
     */
    private function primaryCurrency(Collection $softwareRoots, Collection $cloudTenants): string
    {
        $totals = [];

        foreach ($softwareRoots as $software) {
            $currency = $this->costCurrency($software);
            $totals[$currency] = ($totals[$currency] ?? 0.0) + $this->effectiveMonthlyCost($software);
        }

        foreach ($cloudTenants as $tenant) {
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
     * @param  Collection<int, array{currency: string, monthly: float}>  $softwareRows
     * @param  Collection<int, array{currency: string, monthly: float}>  $cloudRows
     * @return list<array{currency: string, estimated_monthly: float, formatted_monthly: string}>
     */
    private function otherCurrencyTotals(Collection $softwareRows, Collection $cloudRows, string $primaryCurrency): array
    {
        $totals = [];
        $primary = strtoupper($primaryCurrency);

        foreach ($softwareRows as $row) {
            if ($row['currency'] === $primary) {
                continue;
            }

            $totals[$row['currency']] = ($totals[$row['currency']] ?? 0.0) + $row['monthly'];
        }

        foreach ($cloudRows as $row) {
            if ($row['currency'] === $primary) {
                continue;
            }

            $totals[$row['currency']] = ($totals[$row['currency']] ?? 0.0) + $row['monthly'];
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

    private function currencyCode(?string $currency): string
    {
        return strtoupper($currency ?: 'GBP');
    }

    private function formatMoney(string $currency, float $amount): string
    {
        return strtoupper($currency).' '.number_format($amount, 2);
    }
}
