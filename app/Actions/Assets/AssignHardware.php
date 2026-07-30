<?php

namespace App\Actions\Assets;

use App\Enums\HardwareStatus;
use App\Models\Hardware;
use App\Models\Userware;
use Illuminate\Validation\ValidationException;

class AssignHardware
{
    /**
     * @throws ValidationException
     */
    public function handle(Hardware $hardware, ?Userware $userware): Hardware
    {
        if ($userware !== null && $userware->organization_id !== $hardware->organization_id) {
            throw ValidationException::withMessages([
                'assigned_userware_id' => __('The selected identity belongs to another organization.'),
            ]);
        }

        if ($hardware->status === HardwareStatus::Retired && $userware !== null) {
            throw ValidationException::withMessages([
                'assigned_userware_id' => __('Retired hardware cannot be assigned.'),
            ]);
        }

        $hardware->forceFill([
            'assigned_userware_id' => $userware?->id,
            'status' => $userware === null
                ? ($hardware->status === HardwareStatus::Retired ? HardwareStatus::Retired : HardwareStatus::Available)
                : HardwareStatus::Assigned,
        ])->save();

        return $hardware->refresh();
    }
}
