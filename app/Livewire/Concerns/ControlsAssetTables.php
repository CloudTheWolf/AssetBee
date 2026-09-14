<?php

namespace App\Livewire\Concerns;

use App\Support\AssetTablePagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Session;

trait ControlsAssetTables
{
    #[Session]
    public int $perPage = 10;

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(mixed $value): void
    {
        $perPage = (int) $value;

        $this->perPage = in_array($perPage, AssetTablePagination::PER_PAGE_OPTIONS, true)
            ? $perPage
            : AssetTablePagination::PER_PAGE_OPTIONS[0];
    }

    public function sort(string $column): void
    {
        if (! in_array($column, $this->sortableColumns(), true)) {
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

    protected function currentSortColumn(): string
    {
        return in_array($this->sortBy, $this->sortableColumns(), true) ? $this->sortBy : 'name';
    }

    protected function currentSortDirection(): string
    {
        return $this->sortDirection === 'desc' ? 'desc' : 'asc';
    }

    protected function rowsPerPage(): int
    {
        return in_array($this->perPage, AssetTablePagination::PER_PAGE_OPTIONS, true)
            ? $this->perPage
            : AssetTablePagination::PER_PAGE_OPTIONS[0];
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function orderByAssignedUserware(Builder $query, string $direction): void
    {
        $table = $query->getModel()->getTable();
        $assignedName = '(select name from userwares where userwares.id = '.$table.'.assigned_userware_id and userwares.deleted_at is null limit 1)';

        $query
            ->orderByRaw($assignedName.' is null')
            ->orderByRaw($assignedName.' '.$direction)
            ->orderBy($table.'.id');
    }

    /**
     * @return list<string>
     */
    abstract protected function sortableColumns(): array;
}
