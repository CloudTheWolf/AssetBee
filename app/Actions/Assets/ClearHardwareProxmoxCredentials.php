<?php

namespace App\Actions\Assets;

use App\Models\Hardware;

class ClearHardwareProxmoxCredentials
{
    public function handle(Hardware $hardware): Hardware
    {
        $hardware->update([
            'proxmox_credentials' => null,
            'proxmox_credentials_verified_at' => null,
        ]);

        return $hardware->refresh();
    }
}
