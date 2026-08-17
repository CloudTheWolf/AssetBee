<?php

namespace App\Actions\Assets;

use App\Actions\Assets\Concerns\NormalizesSoftwareSeatManager;
use App\Enums\SoftwareBillingInterval;
use App\Enums\SoftwareLicenseType;
use App\Enums\SoftwareStatus;
use App\Models\Organization;
use App\Models\Software;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateSoftware
{
    use NormalizesSoftwareSeatManager;

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
            ...$this->seatManagerRules($organization),
            'status' => ['required', Rule::enum(SoftwareStatus::class)],
            'expires_at' => ['nullable', 'date'],
            'is_recurring' => ['sometimes', 'boolean'],
            'billing_interval' => ['nullable', Rule::enum(SoftwareBillingInterval::class)],
            'billing_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'next_billing_at' => ['nullable', 'date'],
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

        $validated['is_recurring'] = (bool) ($validated['is_recurring'] ?? false);
        $validated['currency'] = strtoupper($validated['currency'] ?? 'GBP');

        if (! $validated['is_recurring']) {
            $validated['billing_interval'] = null;
            $validated['billing_amount'] = null;
            $validated['next_billing_at'] = null;
        } else {
            Validator::make($validated, [
                'billing_interval' => ['required', Rule::enum(SoftwareBillingInterval::class)],
            ])->validate();
        }

        $validated = $this->normalizeSeatManager($validated, $organization);

        return $organization->softwares()->create($validated);
    }
}
