<?php

namespace App\Actions\RemoteAdmin;

use App\Models\DeviceGroup;
use App\Models\Organization;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateDeviceGroup
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Organization $organization, array $input): DeviceGroup
    {
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        return $organization->deviceGroups()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);
    }
}
