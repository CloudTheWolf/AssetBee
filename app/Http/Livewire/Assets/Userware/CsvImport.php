<?php

namespace App\Http\Livewire\Assets\Userware;

use App\Enum\ImportTaskType;
use App\Jobs\PerformUserwareImport;
use App\Models\ImportTask;
use App\Models\Owner;
use League\Csv\Reader;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class CsvImport extends Component
{
    use WithFileUploads;

    #[Validate('required|max:2024')]
    public $csv;

    public function render()
    {
        return view('assets.userware.csv-import');
    }

    public function moveCsv()
    {
        $finalPath = $this->csv->store('usersware-import', 'public');
        $task = new ImportTask();
        $task->path = $finalPath;
        $task->type = ImportTaskType::USERWARE;
        $task->save();
        PerformUserwareImport::dispatch();
        $this->dispatch('toast-message', ['message' => 'done']);

    }

}
