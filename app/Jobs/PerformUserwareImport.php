<?php

namespace App\Jobs;

use App\Enum\ImportTaskType;
use App\Models\ImportTask;
use App\Models\Owner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use League\Csv\Exception;
use League\Csv\Reader;
use League\Csv\UnavailableStream;

class PerformUserwareImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //Nothing to construct
    }

    /**
     * Execute the job.
     * @throws UnavailableStream
     * @throws Exception
     */
    public function handle(): void
    {
        $importTasks = ImportTask::whereDone(0)->whereType(ImportTaskType::USERWARE)->get();
        foreach($importTasks as $task)
        {
            echo "Starting from $task->path\n";
            $csv = Reader::createFromPath(storage_path('app/public/'.$task->path), 'r');
            $csv->setHeaderOffset(0);
            $records = iterator_to_array($csv->getRecords());
            $chunkSize = 100;

            foreach (array_chunk($records, $chunkSize) as $chunkIndex => $chunk) {
                foreach ($chunk as $record) {
                    $values = array_values($record);

                    $email = $values[2];

                    // Use updateOrCreate to insert or update the record
                    Owner::updateOrCreate(
                        ['email' => $email],
                        [
                            'first_name' => $values[0],
                            'last_name' => $values[1],
                            'email' => $email,
                        ]
                    );
                }

                usleep(100000);
            }

            $task->done = 1;
            $task->save();
            Storage::delete(storage_path('app/public/'.$task->path));
        }
    }
}
