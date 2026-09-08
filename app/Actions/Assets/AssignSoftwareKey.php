<?php

namespace App\Actions\Assets;

use App\Enums\SoftwareLicenseType;
use App\Models\Software;
use App\Models\SoftwareAssignment;
use App\Models\SoftwareKey;
use App\Models\Userware;
use Illuminate\Validation\ValidationException;

class AssignSoftwareKey
{
    /**
     * @throws ValidationException
     */
    public function handle(Software $software, SoftwareKey $key, Userware $userware, ?string $notes = null): SoftwareAssignment
    {
        if ($software->license_type !== SoftwareLicenseType::Key) {
            throw ValidationException::withMessages([
                'software_key_id' => __('License keys can only be assigned for key-based software.'),
            ]);
        }

        if ($key->software_id !== $software->id) {
            throw ValidationException::withMessages([
                'software_key_id' => __('The selected key does not belong to this software.'),
            ]);
        }

        if ($userware->organization_id !== $software->organization_id) {
            throw ValidationException::withMessages([
                'userware_id' => __('The selected identity belongs to another organization.'),
            ]);
        }

        if ($key->isAssigned()) {
            throw ValidationException::withMessages([
                'software_key_id' => __('This license key is already assigned.'),
            ]);
        }

        $existing = SoftwareAssignment::query()
            ->where('software_id', $software->id)
            ->where('userware_id', $userware->id)
            ->first();

        if ($existing !== null) {
            throw ValidationException::withMessages([
                'userware_id' => __('This identity already has a key for this license.'),
            ]);
        }

        return SoftwareAssignment::query()->create([
            'software_id' => $software->id,
            'userware_id' => $userware->id,
            'software_key_id' => $key->id,
            'assigned_at' => now(),
            'notes' => $notes,
        ]);
    }
}
