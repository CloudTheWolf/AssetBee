<?php

namespace App\Actions\Assets;

use App\Actions\Assets\Concerns\NormalizesHardwareAttributes;
use App\Enums\HardwareCategory;
use App\Enums\HardwareStatus;
use App\Models\Hardware;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateHardware
{
    use NormalizesHardwareAttributes;

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Hardware $hardware, array $input): Hardware
    {
        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'asset_tag' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('hardwares', 'asset_tag')
                    ->where('organization_id', $hardware->organization_id)
                    ->ignore($hardware->id),
            ],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'category' => ['required', Rule::enum(HardwareCategory::class)],
            'status' => ['required', Rule::enum(HardwareStatus::class)],
            'purchased_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            ...$this->hardwareAttributeRules(),
        ]);

        $this->afterHardwareValidation($validator, $input);

        $validated = $this->normalizeHardwareAttributes($validator->validate());

        $wasVmHost = $hardware->is_vm_host;
        $hardware->update($validated);

        if ($wasVmHost && ! $hardware->fresh()->is_vm_host) {
            $hardware->virtualwares()->update(['host_hardware_id' => null]);
        }

        return $hardware->refresh();
    }
}
