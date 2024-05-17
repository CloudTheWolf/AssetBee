<?php

namespace App\Http\Livewire\Assets\Hardware;

use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use App\Models\HardwareAsset;

class HardwareTable extends DataTableComponent
{
    protected $model = HardwareAsset::class;

    public function configure(): void
    {
        $this->setPrimaryKey('id');
    }

    public function columns(): array
    {
        return [
            Column::make("Id", "id")
                ->sortable(),
            Column::make("Asset name", "asset_name")
                ->sortable(),
            Column::make("Product name", "product_name")
                ->sortable(),
            Column::make("Custom Asset id", "custom_tracking_id")
                ->sortable()
        ];
    }
}
