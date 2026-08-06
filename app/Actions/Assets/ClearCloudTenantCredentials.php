<?php

namespace App\Actions\Assets;

use App\Models\CloudTenant;

class ClearCloudTenantCredentials
{
    public function handle(CloudTenant $cloudTenant): CloudTenant
    {
        $cloudTenant->update([
            'credentials' => null,
            'credentials_verified_at' => null,
        ]);

        return $cloudTenant->refresh();
    }
}
