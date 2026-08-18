<?php

use App\Support\CurrentOrganization;
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
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Reports') }}</flux:heading>
        <flux:text>{{ __('Inventory findings for :name.', ['name' => CurrentOrganization::require()->name]) }}</flux:text>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($this->catalog as $item)
            <a
                href="{{ route('reports.show', $item['report']->value) }}"
                wire:navigate
                class="rounded-xl border border-zinc-200 p-5 transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <flux:heading size="lg">{{ $item['report']->title() }}</flux:heading>
                        <flux:text class="mt-1">{{ $item['report']->description() }}</flux:text>
                    </div>
                    <flux:badge :color="$item['count'] > 0 ? 'amber' : 'zinc'">
                        {{ $item['count'] }}
                    </flux:badge>
                </div>
            </a>
        @endforeach
    </div>
</div>
