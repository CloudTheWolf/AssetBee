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
            <flux:text>{{ __('Est. monthly software spend') }}</flux:text>
            <flux:heading size="xl" class="mt-2 tabular-nums">{{ $costs['formatted_monthly'] }}</flux:heading>
            @foreach ($costs['other_currencies'] as $other)
                <flux:text class="mt-1">{{ __('Also :amount / mo', ['amount' => $other['formatted_monthly']]) }}</flux:text>
            @endforeach
        </div>
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text>{{ __('Est. annual software spend') }}</flux:text>
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
                <flux:text>{{ __('Projected from recurring software billing schedules.') }}</flux:text>
            </div>

            @if (collect($insights['monthly_forecast'])->sum('total') > 0)
                <div class="flex h-48 items-end gap-2">
                    @foreach ($insights['monthly_forecast'] as $month)
                        <div class="flex flex-1 flex-col items-center gap-2" title="{{ $month['formatted'] }}">
                            <div class="flex h-40 w-full items-end">
                                <div
                                    class="w-full rounded-t-md bg-accent/80 dark:bg-accent/70"
                                    style="height: {{ max($month['percent'], $month['total'] > 0 ? 4 : 0) }}%"
                                ></div>
                            </div>
                            <span class="text-xs text-zinc-500">{{ $month['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <flux:text>{{ __('Add recurring software billing amounts to see a spend forecast.') }}</flux:text>
            @endif
        </div>

        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <div class="mb-4">
                <flux:heading size="lg">{{ __('Top software by monthly cost') }}</flux:heading>
                <flux:text>{{ __('Normalized from monthly, quarterly, and yearly intervals.') }}</flux:text>
            </div>

            @forelse ($insights['top_software_costs'] as $softwareCost)
                <a href="{{ route('assets.software.show', $softwareCost['id']) }}" wire:navigate class="mb-3 block last:mb-0">
                    <div class="mb-1 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="truncate font-medium">{{ $softwareCost['name'] }}</div>
                            @if ($softwareCost['vendor'])
                                <flux:text class="truncate">{{ $softwareCost['vendor'] }}</flux:text>
                            @endif
                        </div>
                        <div class="shrink-0 tabular-nums text-sm font-medium">{{ $softwareCost['formatted'] }}</div>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <div class="h-full rounded-full bg-accent" style="width: {{ $softwareCost['percent'] }}%"></div>
                    </div>
                </a>
            @empty
                <flux:text>{{ __('No recurring software costs recorded yet.') }}</flux:text>
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
