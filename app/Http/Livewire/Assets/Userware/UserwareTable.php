<?php

namespace App\Http\Livewire\Assets\Userware;

use App\Models\Owner;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class UserwareTable extends DataTableComponent
{
    protected $model = Owner::class;

    public function configure(): void
    {
        $this->setPrimaryKey('id');
    }

    public function columns(): array
    {
        return [
            Column::make("Id", "id")
                ->sortable(),
            Column::make("First name", "first_name")
                ->sortable(),
            Column::make("Last name", "last_name")
                ->sortable(),
            Column::make("Email Address", "email")
                ->sortable()
        ];
    }
}
