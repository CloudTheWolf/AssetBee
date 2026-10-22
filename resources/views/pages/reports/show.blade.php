<?php

use App\Enums\InventoryReport;
use App\Support\CurrentOrganization;
use App\Support\OrganizationInventoryReports;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Report')] class extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public const VERSION_FILTER_NONE = '__none__';

    #[Locked]
    public InventoryReport $reportType;

    public string $search = '';

    public string $versionFilter = '';

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public function mount(string $report): void
    {
        $this->authorize('viewReports', CurrentOrganization::require());

        $this->reportType = InventoryReport::tryFrom($report) ?? abort(404);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingVersionFilter(): void
    {
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        if (! $this->canSortByVersion() || $column !== 'version') {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function canSortByVersion(): bool
    {
        return $this->reportType === InventoryReport::DroneVersion;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function reportRows(): Collection
    {
        return app(OrganizationInventoryReports::class)
            ->rows(CurrentOrganization::require(), $this->reportType);
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function versionOptions(): array
    {
        if (! $this->canSortByVersion()) {
            return [];
        }

        return $this->reportRows
            ->pluck('version')
            ->filter(fn (mixed $version): bool => is_string($version) && $version !== '')
            ->unique()
            ->sort(fn (string $left, string $right): int => version_compare($left, $right))
            ->values()
            ->all();
    }

    #[Computed]
    public function hasDevicesWithoutVersion(): bool
    {
        if (! $this->canSortByVersion()) {
            return false;
        }

        return $this->reportRows->contains(
            fn (array $row): bool => blank($row['version'] ?? null),
        );
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $search = mb_strtolower(trim($this->search));
        $versionFilter = $this->canSortByVersion() ? $this->versionFilter : '';

        $items = $this->reportRows
            ->when($search !== '', function (Collection $rows) use ($search): Collection {
                return $rows->filter(function (array $row) use ($search): bool {
                    $haystack = mb_strtolower(implode(' ', array_filter([
                        $row['name'],
                        $row['serial_number'],
                        $row['assigned_to'],
                        $row['category_label'],
                        $row['detail'],
                        $row['version'] ?? null,
                    ])));

                    return str_contains($haystack, $search);
                })->values();
            })
            ->when($versionFilter !== '', function (Collection $rows) use ($versionFilter): Collection {
                return $rows->filter(function (array $row) use ($versionFilter): bool {
                    $version = $row['version'] ?? null;

                    if ($versionFilter === self::VERSION_FILTER_NONE) {
                        return blank($version);
                    }

                    return is_string($version) && $version === $versionFilter;
                })->values();
            });

        if ($this->canSortByVersion() && $this->sortBy === 'version') {
            $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';

            $items = $items->sort(function (array $left, array $right) use ($direction): int {
                $leftVersion = is_string($left['version'] ?? null) ? $left['version'] : null;
                $rightVersion = is_string($right['version'] ?? null) ? $right['version'] : null;

                if ($leftVersion === null && $rightVersion === null) {
                    return strcasecmp((string) $left['name'], (string) $right['name']);
                }

                if ($leftVersion === null) {
                    return 1;
                }

                if ($rightVersion === null) {
                    return -1;
                }

                $comparison = version_compare($leftVersion, $rightVersion);

                if ($comparison === 0) {
                    return strcasecmp((string) $left['name'], (string) $right['name']);
                }

                return $direction === 'desc' ? -$comparison : $comparison;
            })->values();
        }

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

    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            :placeholder="__('Search devices…')"
            class="max-w-md flex-1 sm:min-w-56"
        />

        @if ($this->canSortByVersion())
            <flux:select wire:model.live="versionFilter" class="sm:w-56" data-test="version-filter">
                <option value="">{{ __('All versions') }}</option>
                @if ($this->hasDevicesWithoutVersion)
                    <option value="__none__">{{ __('No version available') }}</option>
                @endif
                @foreach ($this->versionOptions as $version)
                    <option value="{{ $version }}">{{ $version }}</option>
                @endforeach
            </flux:select>
        @endif
    </div>

    <flux:table :paginate="$this->rows">
        <flux:table.columns>
            <flux:table.column>{{ __('Device') }}</flux:table.column>
            <flux:table.column>{{ __('Type') }}</flux:table.column>
            <flux:table.column>{{ __('Assigned to') }}</flux:table.column>
            @if ($this->canSortByVersion())
                <flux:table.column
                    sortable
                    :sorted="$sortBy === 'version'"
                    :direction="$sortDirection"
                    wire:click="sort('version')"
                    data-test="version-sort"
                >{{ $reportType->detailHeading() }}</flux:table.column>
            @else
                <flux:table.column>{{ $reportType->detailHeading() }}</flux:table.column>
            @endif
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
                    <flux:table.cell>{{ $row['category_label'] }}</flux:table.cell>
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
