<?php

namespace App\Actions\Assets;

use App\Enums\SoftwareCostSyncProvider;
use App\Models\Software;

class ClearSoftwareCostSyncSettings
{
    public function handle(Software $software): Software
    {
        $software->update([
            'cost_sync_provider' => SoftwareCostSyncProvider::None,
            'cost_sync_credentials' => null,
            'cost_sync_request' => null,
            'cost_synced_at' => null,
            'cost_sync_error' => null,
        ]);

        return $software->refresh();
    }
}
