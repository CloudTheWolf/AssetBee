<?php

namespace App\Actions\RemoteAdmin;

use App\Models\DeviceGroup;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateDeviceGroup
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(DeviceGroup $deviceGroup, array $input): DeviceGroup
    {
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        $deviceGroup->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        return $deviceGroup->refresh();
    }
}
