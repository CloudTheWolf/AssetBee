<?php

use App\Support\CurrentOrganization;
use App\Support\OrganizationCostBreakdownReport;
use App\Support\OrganizationInventoryReports;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Reports')] class extends Component
{
    use AuthorizesRequests;

    public function mount(): void
    {
        $this->authorize('viewReports', CurrentOrganization::require());
    }

    /**
     * @return list<array{report: \App\Enums\InventoryReport, count: int}>
     */
    #[Computed]
    public function catalog(): array
    {
        return app(OrganizationInventoryReports::class)->catalog(CurrentOrganization::require());
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
    public function costBreakdown(): array
    {
        return app(OrganizationCostBreakdownReport::class)->for(CurrentOrganization::require());
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Reports') }}</flux:heading>
        <flux:text>{{ __('Cost and inventory findings for :name.', ['name' => CurrentOrganization::require()->name]) }}</flux:text>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <a
                href="{{ route('reports.cost-breakdown') }}"
                wire:navigate
                class="flex items-start justify-between gap-3 rounded-lg transition hover:bg-zinc-50 dark:hover:bg-zinc-900"
            >
                <div class="min-w-0">
                    <flux:heading size="lg">{{ __('Cost Breakdown') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Estimated monthly spend by software and cloud, with sub-products nested under their parent.') }}</flux:text>
                </div>
                <flux:badge :color="$this->costBreakdown['line_item_count'] > 0 ? 'amber' : 'zinc'">
                    {{ $this->costBreakdown['line_item_count'] }}
                </flux:badge>
            </a>
            <div class="mt-4">
                <flux:button
                    :href="route('reports.cost-breakdown.pdf')"
                    size="sm"
                    variant="ghost"
                    icon="arrow-down-tray"
                >
                    {{ __('Download PDF') }}
                </flux:button>
            </div>
        </div>

        @foreach ($this->catalog as $item)
            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <a
                    href="{{ route('reports.show', $item['report']->value) }}"
                    wire:navigate
                    class="flex items-start justify-between gap-3 rounded-lg transition hover:bg-zinc-50 dark:hover:bg-zinc-900"
                >
                    <div class="min-w-0">
                        <flux:heading size="lg">{{ $item['report']->title() }}</flux:heading>
                        <flux:text class="mt-1">{{ $item['report']->description() }}</flux:text>
                    </div>
                    <flux:badge :color="$item['count'] > 0 ? 'amber' : 'zinc'">
                        {{ $item['count'] }}
                    </flux:badge>
                </a>
                <div class="mt-4">
                    <flux:button
                        :href="route('reports.pdf', $item['report']->value)"
                        size="sm"
                        variant="ghost"
                        icon="arrow-down-tray"
                    >
                        {{ __('Download PDF') }}
                    </flux:button>
                </div>
            </div>
        @endforeach
    </div>
</div>
