<?php

namespace App\Actions\RemoteAdmin;

use App\Models\DeviceGroup;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SyncDeviceGroupHardware
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(DeviceGroup $deviceGroup, array $input): DeviceGroup
    {
        $validated = Validator::make($input, [
            'hardware_ids' => ['required', 'array'],
            'hardware_ids.*' => [
                'integer',
                'exists:hardwares,id,organization_id,'.$deviceGroup->organization_id.',deleted_at,NULL',
            ],
        ])->validate();

        $deviceGroup->hardwares()->sync($validated['hardware_ids']);

        return $deviceGroup->refresh();
    }
}
