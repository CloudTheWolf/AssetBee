<?php

namespace App\Actions\Assets;

use App\Models\Hardware;
use App\Models\Userware;
use App\Models\Virtualware;
use Illuminate\Validation\ValidationException;

class AssignVirtualware
{
    /**
     * @throws ValidationException
     */
    public function handle(Virtualware $virtualware, ?Userware $userware = null, ?Hardware $host = null, bool $updateHost = false): Virtualware
    {
        if ($userware !== null && $userware->organization_id !== $virtualware->organization_id) {
            throw ValidationException::withMessages([
                'assigned_userware_id' => __('The selected identity belongs to another organization.'),
            ]);
        }

        if ($updateHost) {
            if ($host !== null && $host->organization_id !== $virtualware->organization_id) {
                throw ValidationException::withMessages([
                    'host_hardware_id' => __('The selected host belongs to another organization.'),
                ]);
            }

            $virtualware->host_hardware_id = $host?->id;
        }

        $virtualware->assigned_userware_id = $userware?->id;
        $virtualware->save();

        return $virtualware->refresh();
    }
}
