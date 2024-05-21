<?php

namespace App\Http\Livewire\Assets\Userware;

use App\Models\Owner;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class UserwareTable extends DataTableComponent
{
    protected $model = Owner::class;

    public function configure(): void
    {
        $this->setPrimaryKey('id')->setTableRowUrl(function($row) {
            return url('/admin.users.show', $row);
        })
            ->setTableRowUrlTarget(function($row) {
                return '_self';
            });
    }

    public function columns(): array
    {
        return [
            Column::make("Id", "id")
                ->sortable(),
            Column::make("First name", "first_name")
                ->searchable()
                ->sortable(),
            Column::make("Last name", "last_name")
                ->searchable()
                ->sortable(),
            Column::make("Email Address", "email")
                ->searchable()
                ->sortable()
        ];
    }

    public function builder(): Builder
    {
        return Owner::query()
            ->when($this->columnSearch['first_name'] ?? null, fn ($query, $first_name) => $query->where('owners.first_name', 'like', '%' . $first_name . '%'))
            ->when($this->columnSearch['last_name'] ?? null, fn ($query, $last_name) => $query->where('owners.last_name', 'like', '%' . $last_name . '%'))
            ->when($this->columnSearch['email'] ?? null, fn ($query, $email) => $query->where('owners.email', 'like', '%' . $email. '%'));
    }
}
