<?php

namespace App\Http\Livewire\Assets;

use App\Models\HardwareVendor;
use Livewire\Component;

class HadrwareVendorSearch extends Component
{

    public $showresult = false;
    public $search = '';
    public $records;
    public $vendorDetails;

    public function searchResult(){
        if(empty($this->search) || strlen($this->search) < 2) {
            $this->showresult = false;
            return;
        };
        $this->records = HardwareVendor::query()
            ->where('name','like',"%$this->search%")
            ->orderBy('name')
            ->limit(5)
            ->get();
        $this->showresult = true;
    }

    public function fetchVendorDetail($id = 0){
        $record = HardwareVendor::query()->whereId($id)->first();
        $this->search = $record->name;
        $this->vendorDetails = $record;
        $this->showDiv = false;

    }

    public function render()
    {
        return view('assets.hadrware-vendor-search');
    }
}
