<?php

namespace App\Jobs;

use App\Actions\Assets\PurgeSoftDeletedAssets as PurgeSoftDeletedAssetsAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PurgeSoftDeletedAssets implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?int $retentionDays = null) {}

    public function handle(PurgeSoftDeletedAssetsAction $purgeSoftDeletedAssets): void
    {
        $result = $purgeSoftDeletedAssets->handle($this->retentionDays);

        Log::info('Purged soft-deleted assets.', $result);
    }
}
