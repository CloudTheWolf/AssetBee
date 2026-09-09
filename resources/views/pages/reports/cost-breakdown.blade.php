<?php

use App\Support\CurrentOrganization;
use App\Support\OrganizationCostBreakdownReport;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Cost Breakdown')] class extends Component
{
    use AuthorizesRequests;

    public string $search = '';

    public function mount(): void
    {
        $this->authorize('viewReports', CurrentOrganization::require());
    }

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
     *     software: list<array<string, mixed>>,
     *     cloud: list<array<string, mixed>>
     * }
     */
    #[Computed]
    public function report(): array
    {
        return app(OrganizationCostBreakdownReport::class)->for(CurrentOrganization::require());
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function software(): array
    {
        return $this->filterRows($this->report['software']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function cloud(): array
    {
        return $this->filterRows($this->report['cloud']);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function filterRows(array $rows): array
    {
        $search = mb_strtolower(trim($this->search));

        if ($search === '') {
            return $rows;
        }

        return array_values(array_filter($rows, function (array $row) use ($search): bool {
            $haystack = mb_strtolower(implode(' ', array_filter([
                $row['name'] ?? null,
                $row['vendor'] ?? null,
            ])));

            if (str_contains($haystack, $search)) {
                return true;
            }

            foreach ($row['children'] ?? [] as $child) {
                $childHaystack = mb_strtolower(implode(' ', array_filter([
                    $child['name'] ?? null,
                    $child['vendor'] ?? null,
                ])));

                if (str_contains($childHaystack, $search)) {
                    return true;
                }
            }

            return false;
        }));
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Cost Breakdown') }}</flux:heading>
            <flux:text>{{ __('Estimated monthly spend by software and cloud, with sub-products nested under their parent.') }}</flux:text>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <flux:button :href="route('reports.cost-breakdown.pdf')" icon="arrow-down-tray">
                {{ __('Download PDF') }}
            </flux:button>
            <flux:button :href="route('reports.index')" wire:navigate icon="arrow-left" variant="ghost">
                {{ __('All reports') }}
            </flux:button>
        </div>
    </div>

    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:text>{{ __('Estimated monthly') }}</flux:text>
        <flux:heading size="xl" class="mt-1 tabular-nums">{{ $this->report['formatted_monthly'] }}</flux:heading>
        @foreach ($this->report['other_currencies'] as $other)
            <flux:text class="mt-1">{{ __('Also :amount / mo', ['amount' => $other['formatted_monthly']]) }}</flux:text>
        @endforeach
    </div>

    <flux:input
        wire:model.live.debounce.300ms="search"
        icon="magnifying-glass"
        :placeholder="__('Search name or vendor…')"
        class="max-w-md"
    />

    <section class="flex flex-col gap-3">
        <div class="flex items-baseline justify-between gap-3">
            <flux:heading size="lg">{{ __('Software') }}</flux:heading>
            <flux:text class="tabular-nums">{{ $this->report['formatted_software_total'] }}</flux:text>
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 text-left dark:bg-zinc-900">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Name') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Vendor') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Interval') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ __('Monthly') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->software as $parent)
                        <tr wire:key="software-{{ $parent['id'] }}">
                            <td class="px-4 py-3">
                                <a href="{{ $parent['url'] }}" class="font-medium text-accent" wire:navigate>{{ $parent['name'] }}</a>
                            </td>
                            <td class="px-4 py-3">{{ $parent['vendor'] ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $parent['interval'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $parent['formatted'] }}</td>
                        </tr>
                        @foreach ($parent['children'] as $child)
                            <tr wire:key="software-child-{{ $child['id'] }}" class="bg-zinc-50/60 dark:bg-zinc-900/40">
                                <td class="px-4 py-2 pl-10">
                                    <a href="{{ $child['url'] }}" class="text-accent" wire:navigate>{{ $child['name'] }}</a>
                                </td>
                                <td class="px-4 py-2">{{ $child['vendor'] ?? '—' }}</td>
                                <td class="px-4 py-2">{{ $child['interval'] ?? '—' }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $child['formatted'] }}</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center">
                                <flux:text>{{ __('No software costs.') }}</flux:text>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="flex flex-col gap-3">
        <div class="flex items-baseline justify-between gap-3">
            <flux:heading size="lg">{{ __('Cloud') }}</flux:heading>
            <flux:text class="tabular-nums">{{ $this->report['formatted_cloud_total'] }}</flux:text>
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 text-left dark:bg-zinc-900">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Name') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Provider') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Interval') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ __('Monthly') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->cloud as $tenant)
                        <tr wire:key="cloud-{{ $tenant['id'] }}">
                            <td class="px-4 py-3">
                                <a href="{{ $tenant['url'] }}" class="font-medium text-accent" wire:navigate>{{ $tenant['name'] }}</a>
                            </td>
                            <td class="px-4 py-3">{{ $tenant['vendor'] ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $tenant['interval'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $tenant['formatted'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center">
                                <flux:text>{{ __('No cloud costs.') }}</flux:text>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
