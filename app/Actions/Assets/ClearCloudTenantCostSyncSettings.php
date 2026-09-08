<?php

namespace App\Actions\Assets;

use App\Enums\CloudTenantCostSyncProvider;
use App\Models\CloudTenant;

class ClearCloudTenantCostSyncSettings
{
    public function handle(CloudTenant $cloudTenant): CloudTenant
    {
        $cloudTenant->update([
            'cost_sync_provider' => CloudTenantCostSyncProvider::None,
            'cost_sync_request' => null,
            'cost_synced_at' => null,
            'cost_sync_error' => null,
        ]);

        return $cloudTenant->refresh();
    }
}
