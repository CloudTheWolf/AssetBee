<?php

namespace App\Http\Livewire\Assets\Hardware;

use App\Models\HardwareVendor;
use Livewire\Component;

class HadrwareVendorSearch extends Component
{

    public $showResult = false;
    public $search = '';
    public $records;
    public $vendorDetails;

    public function searchResult(){
        if(empty($this->search) || strlen($this->search) < 2) {
            $this->showResult = false;
            return;
        };
        $this->records = HardwareVendor::query()
            ->where('name','like',"%$this->search%")
            ->orderBy('name')
            ->limit(5)
            ->get();
        $this->showResult = true;
    }

    public function fetchVendorDetail($id = 0){
        $record = HardwareVendor::query()->whereId($id)->first();
        $this->search = $record->name;
        $this->vendorDetails = $record;
        $this->showResult = false;

    }

    public function render()
    {
        return view('assets.hardware.hardware-vendor-search');
    }
}
