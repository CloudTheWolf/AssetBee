<?php

use App\Support\CurrentOrganization;
use App\Support\OrganizationDashboardInsights;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    #[Computed]
    public function insights(): array
    {
        return app(OrganizationDashboardInsights::class)->for(CurrentOrganization::require());
    }
}; ?>

@php
    $insights = $this->insights;
    $inventory = $insights['inventory'];
    $costs = $insights['costs'];
@endphp

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>
        <flux:text>{{ CurrentOrganization::require()->name }}</flux:text>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <a href="{{ route('assets.userware.index') }}" wire:navigate class="rounded-xl border border-zinc-200 p-5 transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900">
            <flux:text>{{ __('Userware') }}</flux:text>
            <flux:heading size="xl" class="mt-2">{{ $inventory['userware'] }}</flux:heading>
        </a>
        <a href="{{ route('assets.hardware.index') }}" wire:navigate class="rounded-xl border border-zinc-200 p-5 transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900">
            <flux:text>{{ __('Hardware') }}</flux:text>
            <flux:heading size="xl" class="mt-2">{{ $inventory['hardware'] }}</flux:heading>
        </a>
        <a href="{{ route('assets.cloud-tenants.index') }}" wire:navigate class="rounded-xl border border-zinc-200 p-5 transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900">
            <flux:text>{{ __('Cloud Tenants') }}</flux:text>
            <flux:heading size="xl" class="mt-2">{{ $inventory['cloud_tenants'] }}</flux:heading>
        </a>
        <a href="{{ route('assets.virtualware.index') }}" wire:navigate class="rounded-xl border border-zinc-200 p-5 transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900">
            <flux:text>{{ __('Virtualware') }}</flux:text>
            <flux:heading size="xl" class="mt-2">{{ $inventory['virtualware'] }}</flux:heading>
        </a>
        <a href="{{ route('assets.software.index') }}" wire:navigate class="rounded-xl border border-zinc-200 p-5 transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900">
            <flux:text>{{ __('Software') }}</flux:text>
            <flux:heading size="xl" class="mt-2">{{ $inventory['software'] }}</flux:heading>
        </a>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text>{{ __('Est. monthly spend') }}</flux:text>
            <flux:heading size="xl" class="mt-2 tabular-nums">{{ $costs['formatted_monthly'] }}</flux:heading>
            @foreach ($costs['other_currencies'] as $other)
                <flux:text class="mt-1">{{ __('Also :amount / mo', ['amount' => $other['formatted_monthly']]) }}</flux:text>
            @endforeach
        </div>
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text>{{ __('Est. annual spend') }}</flux:text>
            <flux:heading size="xl" class="mt-2 tabular-nums">{{ $costs['formatted_annual'] }}</flux:heading>
        </div>
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text>{{ __('Due in next 30 days') }}</flux:text>
            <flux:heading size="xl" class="mt-2 tabular-nums">{{ $costs['formatted_upcoming_30_days'] }}</flux:heading>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <div class="mb-4">
                <flux:heading size="lg">{{ __('Estimated spend (12 months)') }}</flux:heading>
                <flux:text>{{ __('Software and cloud costs. Estimates use the previous month\'s actuals; actuals finalize after the 5th.') }}</flux:text>
            </div>

            @if (collect($insights['monthly_forecast'])->sum('total') > 0)
                <div class="mb-3 flex flex-wrap items-center gap-4 text-xs text-zinc-500">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="size-2.5 rounded-sm bg-accent/80 dark:bg-accent/70"></span>
                        {{ __('Actual') }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="size-2.5 rounded-sm bg-zinc-300 dark:bg-zinc-600"></span>
                        {{ __('Estimated') }}
                    </span>
                </div>
                <div class="flex gap-3">
                    <div class="flex h-40 shrink-0 flex-col justify-between py-0.5 text-right text-xs tabular-nums text-zinc-500">
                        @foreach ($insights['monthly_forecast_y_axis'] as $tick)
                            <span>{{ $tick['label'] }}</span>
                        @endforeach
                    </div>
                    <div class="flex min-w-0 flex-1 flex-col">
                        <div class="relative flex h-40 items-end gap-2 border-l border-zinc-200 pl-2 dark:border-zinc-700">
                            <div class="pointer-events-none absolute inset-y-0 left-0 right-0 flex flex-col justify-between py-0.5">
                                <div class="border-t border-dashed border-zinc-200 dark:border-zinc-700"></div>
                                <div class="border-t border-dashed border-zinc-200 dark:border-zinc-700"></div>
                                <div class="border-t border-zinc-200 dark:border-zinc-700"></div>
                            </div>
                            @foreach ($insights['monthly_forecast'] as $month)
                                @php
                                    $tooltip = match ($month['mode']) {
                                        'both' => __('Actual :actual · Est. :estimated', [
                                            'actual' => $month['formatted_actual'],
                                            'estimated' => $month['formatted_estimated'],
                                        ]),
                                        'actual' => __('Actual :amount', ['amount' => $month['formatted_actual']]),
                                        default => __('Est. :amount', ['amount' => $month['formatted_estimated']]),
                                    };
                                @endphp
                                <div class="relative z-10 flex h-full flex-1 items-end" title="{{ $tooltip }}">
                                    <div
                                        class="flex w-full flex-col justify-end overflow-hidden rounded-t-md"
                                        style="height: {{ max($month['percent'], $month['total'] > 0 ? 4 : 0) }}%"
                                    >
                                        @if ($month['estimated_segment_percent'] > 0)
                                            <div
                                                class="w-full bg-zinc-300 dark:bg-zinc-600"
                                                style="height: {{ $month['estimated_segment_percent'] }}%"
                                            ></div>
                                        @endif
                                        @if ($month['actual_segment_percent'] > 0)
                                            <div
                                                class="w-full bg-accent/80 dark:bg-accent/70"
                                                style="height: {{ $month['actual_segment_percent'] }}%"
                                            ></div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-2 flex gap-2 pl-2">
                            @foreach ($insights['monthly_forecast'] as $month)
                                <div class="flex-1 text-center text-xs text-zinc-500">{{ $month['label'] }}</div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @else
                <flux:text>{{ __('Add recurring billing or sync licence and cloud costs to see a spend forecast.') }}</flux:text>
            @endif
        </div>

        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <div class="mb-4">
                <flux:heading size="lg">{{ __('Top costs by month') }}</flux:heading>
                <flux:text>{{ __('Top-level software suites and cloud tenants, normalized to monthly.') }}</flux:text>
            </div>

            @forelse ($insights['top_costs'] as $cost)
                @php
                    $costUrl = $cost['type'] === 'cloud_tenant'
                        ? route('assets.cloud-tenants.show', $cost['id'])
                        : route('assets.software.show', $cost['id']);
                @endphp
                <a href="{{ $costUrl }}" wire:navigate class="mb-3 block last:mb-0">
                    <div class="mb-1 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="truncate font-medium">{{ $cost['name'] }}</div>
                            @if ($cost['vendor'])
                                <flux:text class="truncate">{{ $cost['vendor'] }}</flux:text>
                            @endif
                        </div>
                        <div class="shrink-0 tabular-nums text-sm font-medium">{{ $cost['formatted'] }}</div>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <div class="h-full rounded-full bg-accent" style="width: {{ $cost['percent'] }}%"></div>
                    </div>
                </a>
            @empty
                <flux:text>{{ __('No recurring or synced costs recorded yet.') }}</flux:text>
            @endforelse
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Upcoming renewals') }}</flux:heading>
            <flux:text class="mb-4">{{ __('Next 60 days') }}</flux:text>
            <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($insights['upcoming_renewals'] as $renewal)
                    <li class="flex items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <a href="{{ route('assets.software.show', $renewal['id']) }}" wire:navigate class="font-medium text-accent">
                                {{ $renewal['name'] }}
                            </a>
                            <flux:text>{{ \Illuminate\Support\Carbon::parse($renewal['next_billing_at'])->format('M j, Y') }}</flux:text>
                        </div>
                        <div class="shrink-0 tabular-nums text-sm">{{ $renewal['formatted_amount'] }}</div>
                    </li>
                @empty
                    <li class="py-3"><flux:text>{{ __('No renewals due soon.') }}</flux:text></li>
                @endforelse
            </ul>
        </div>

        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Expiring licenses') }}</flux:heading>
            <flux:text class="mb-4">{{ __('Next 60 days') }}</flux:text>
            <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($insights['expiring_licenses'] as $license)
                    <li class="flex items-center justify-between gap-3 py-3">
                        <a href="{{ route('assets.software.show', $license['id']) }}" wire:navigate class="font-medium text-accent">
                            {{ $license['name'] }}
                        </a>
                        <flux:text class="shrink-0">{{ \Illuminate\Support\Carbon::parse($license['expires_at'])->format('M j, Y') }}</flux:text>
                    </li>
                @empty
                    <li class="py-3"><flux:text>{{ __('No licenses expiring soon.') }}</flux:text></li>
                @endforelse
            </ul>
        </div>

        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Attention') }}</flux:heading>
            <flux:text class="mb-4">{{ __('Inventory that may need action') }}</flux:text>
            <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                <li class="flex items-center justify-between gap-3 py-3">
                    <a href="{{ route('assets.hardware.index') }}" wire:navigate class="font-medium text-accent">{{ __('Unassigned hardware') }}</a>
                    <flux:heading size="lg">{{ $insights['unassigned_hardware'] }}</flux:heading>
                </li>
                @forelse ($insights['underutilized_seats'] as $seat)
                    <li class="flex items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <a href="{{ route('assets.software.show', $seat['id']) }}" wire:navigate class="font-medium text-accent">
                                {{ $seat['name'] }}
                            </a>
                            <flux:text>{{ __(':unused unused seats', ['unused' => $seat['unused']]) }}</flux:text>
                        </div>
                        <flux:text class="shrink-0 tabular-nums">{{ $seat['used'] }} / {{ $seat['total'] }}</flux:text>
                    </li>
                @empty
                    <li class="py-3"><flux:text>{{ __('No underutilized seat licenses flagged.') }}</flux:text></li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
