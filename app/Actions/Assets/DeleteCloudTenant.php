<?php

namespace App\Actions\Assets;

use App\Models\CloudTenant;

class DeleteCloudTenant
{
    public function handle(CloudTenant $cloudTenant): void
    {
        $cloudTenant->virtualwares()->update(['cloud_tenant_id' => null]);
        $cloudTenant->delete();
    }
}
