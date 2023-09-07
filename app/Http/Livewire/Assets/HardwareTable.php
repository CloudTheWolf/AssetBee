<?php

namespace App\Http\Livewire\Assets;

use App\Models\HardwareAsset;
use Livewire\Component;

class HardwareTable extends Component
{
    public $hardwareItems;



    public function mount()
    {
        $this->hardwareItems = (new HardwareAsset())->whereNotDecommed()->get();
    }

    public function render()
    {
        return view('assets.hardware-table');
    }
}
