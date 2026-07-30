<?php

namespace App\Actions\Assets;

use App\Enums\SoftwareLicenseType;
use App\Enums\SoftwareStatus;
use App\Models\Organization;
use App\Models\Software;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateSoftware
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Organization $organization, array $input): Software
    {
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'license_type' => ['required', Rule::enum(SoftwareLicenseType::class)],
            'total_seats' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', Rule::enum(SoftwareStatus::class)],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ])->validate();

        $licenseType = $validated['license_type'] instanceof SoftwareLicenseType
            ? $validated['license_type']
            : SoftwareLicenseType::from($validated['license_type']);

        if ($licenseType === SoftwareLicenseType::Seat) {
            Validator::make($validated, [
                'total_seats' => ['required', 'integer', 'min:1'],
            ])->validate();
        } else {
            $validated['total_seats'] = null;
        }

        return $organization->softwares()->create($validated);
    }
}
