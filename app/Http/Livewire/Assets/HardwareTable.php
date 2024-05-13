<?php

namespace App\Http\Livewire\Assets;

use App\Models\HardwareAsset;
use Livewire\Component;
use Livewire\WithPagination;

class HardwareTable extends Component
{
    use WithPagination;

    public function render()
    {
        return view('assets.hardware-table',[
            'hardwareItems' => HardwareAsset::whereNotDecommed()->paginate(10)
        ]);
    }
}
