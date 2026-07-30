<?php

namespace App\Actions\Assets;

use App\Enums\HardwareCategory;
use App\Enums\HardwareStatus;
use App\Models\Hardware;
use App\Models\Organization;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateHardware
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Organization $organization, array $input): Hardware
    {
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'asset_tag' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('hardwares', 'asset_tag')->where('organization_id', $organization->id),
            ],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'category' => ['required', Rule::enum(HardwareCategory::class)],
            'status' => ['required', Rule::enum(HardwareStatus::class)],
            'purchased_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ])->validate();

        return $organization->hardwares()->create($validated);
    }
}
