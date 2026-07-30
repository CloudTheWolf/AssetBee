<?php

use App\Enums\HardwareStatus;
use App\Enums\SoftwareLicenseType;
use App\Models\Hardware;
use App\Models\Software;
use App\Models\Userware;
use App\Models\Virtualware;
use App\Support\CurrentOrganization;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    #[Computed]
    public function stats(): array
    {
        $organization = CurrentOrganization::require();

        $seatLicenses = Software::query()
            ->withCount('assignments')
            ->where('organization_id', $organization->id)
            ->where('license_type', SoftwareLicenseType::Seat)
            ->get();

        $seatsTotal = $seatLicenses->sum('total_seats');
        $seatsUsed = $seatLicenses->sum('assignments_count');

        return [
            'userware' => Userware::query()->where('organization_id', $organization->id)->count(),
            'hardware' => Hardware::query()->where('organization_id', $organization->id)->count(),
            'virtualware' => Virtualware::query()->where('organization_id', $organization->id)->count(),
            'software' => Software::query()->where('organization_id', $organization->id)->count(),
            'unassigned_hardware' => Hardware::query()
                ->where('organization_id', $organization->id)
                ->where('status', HardwareStatus::Available)
                ->whereNull('assigned_userware_id')
                ->count(),
            'seats_used' => $seatsUsed,
            'seats_total' => $seatsTotal,
        ];
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>
        <flux:text>{{ \App\Support\CurrentOrganization::require()->name }}</flux:text>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('assets.userware.index') }}" wire:navigate class="rounded-xl border border-zinc-200 p-5 transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900">
            <flux:text>{{ __('Userware') }}</flux:text>
            <flux:heading size="xl" class="mt-2">{{ $this->stats['userware'] }}</flux:heading>
        </a>
        <a href="{{ route('assets.hardware.index') }}" wire:navigate class="rounded-xl border border-zinc-200 p-5 transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900">
            <flux:text>{{ __('Hardware') }}</flux:text>
            <flux:heading size="xl" class="mt-2">{{ $this->stats['hardware'] }}</flux:heading>
        </a>
        <a href="{{ route('assets.virtualware.index') }}" wire:navigate class="rounded-xl border border-zinc-200 p-5 transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900">
            <flux:text>{{ __('Virtualware') }}</flux:text>
            <flux:heading size="xl" class="mt-2">{{ $this->stats['virtualware'] }}</flux:heading>
        </a>
        <a href="{{ route('assets.software.index') }}" wire:navigate class="rounded-xl border border-zinc-200 p-5 transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900">
            <flux:text>{{ __('Software') }}</flux:text>
            <flux:heading size="xl" class="mt-2">{{ $this->stats['software'] }}</flux:heading>
        </a>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text>{{ __('Unassigned hardware') }}</flux:text>
            <flux:heading size="xl" class="mt-2">{{ $this->stats['unassigned_hardware'] }}</flux:heading>
        </div>
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text>{{ __('Software seats used') }}</flux:text>
            <flux:heading size="xl" class="mt-2">
                {{ $this->stats['seats_used'] }}
                @if ($this->stats['seats_total'] > 0)
                    <span class="text-base font-normal text-zinc-500">/ {{ $this->stats['seats_total'] }}</span>
                @endif
            </flux:heading>
        </div>
    </div>
</div>
