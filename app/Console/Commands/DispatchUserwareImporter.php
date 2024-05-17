<?php

namespace App\Console\Commands;

use App\Jobs\PerformUserwareImport;
use Illuminate\Console\Command;

class DispatchUserwareImporter extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dispatch:userware-importer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import pending userware from CSV Import';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        PerformUserwareImport::dispatch();

        $this->info("Perform Userware Import Dispatched");
        return 0;
    }
}
