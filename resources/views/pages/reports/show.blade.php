<?php

use App\Enums\InventoryReport;
use App\Support\CurrentOrganization;
use App\Support\OrganizationInventoryReports;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Report')] class extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Locked]
    public InventoryReport $reportType;

    public string $search = '';

    public function mount(string $report): void
    {
        $this->authorize('viewReports', CurrentOrganization::require());

        $this->reportType = InventoryReport::tryFrom($report) ?? abort(404);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $search = mb_strtolower(trim($this->search));

        $items = app(OrganizationInventoryReports::class)
            ->rows(CurrentOrganization::require(), $this->reportType)
            ->when($search !== '', function ($rows) use ($search) {
                return $rows->filter(function (array $row) use ($search): bool {
                    $haystack = mb_strtolower(implode(' ', array_filter([
                        $row['name'],
                        $row['serial_number'],
                        $row['assigned_to'],
                        $row['detail'],
                    ])));

                    return str_contains($haystack, $search);
                })->values();
            });

        $page = $this->getPage();
        $perPage = 25;

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">{{ $reportType->title() }}</flux:heading>
            <flux:text>{{ $reportType->description() }}</flux:text>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <flux:button :href="route('reports.pdf', $reportType->value)" icon="arrow-down-tray">
                {{ __('Download PDF') }}
            </flux:button>
            <flux:button :href="route('reports.index')" wire:navigate icon="arrow-left" variant="ghost">
                {{ __('All reports') }}
            </flux:button>
        </div>
    </div>

    <flux:input
        wire:model.live.debounce.300ms="search"
        icon="magnifying-glass"
        :placeholder="__('Search devices…')"
        class="max-w-md"
    />

    <flux:table :paginate="$this->rows">
        <flux:table.columns>
            <flux:table.column>{{ __('Device') }}</flux:table.column>
            <flux:table.column>{{ __('Type') }}</flux:table.column>
            <flux:table.column>{{ __('Assigned to') }}</flux:table.column>
            <flux:table.column>{{ $reportType->detailHeading() }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->rows as $row)
                <flux:table.row :key="$row['asset_type'].'-'.$row['id']">
                    <flux:table.cell>
                        <a href="{{ $row['url'] }}" class="font-medium text-accent" wire:navigate>{{ $row['name'] }}</a>
                        @if ($row['serial_number'])
                            <flux:text>{{ $row['serial_number'] }}</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $row['asset_type'] === 'hardware' ? __('Hardware') : __('Virtualware') }}
                    </flux:table.cell>
                    <flux:table.cell>{{ $row['assigned_to'] ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $row['detail'] }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4">
                        <div class="py-8 text-center">
                            <flux:text>{{ __('No devices match this report.') }}</flux:text>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
