<?php

namespace App\Actions\Assets;

use App\Models\Software;
use App\Models\SoftwareAssignment;
use App\Models\Userware;
use Illuminate\Validation\ValidationException;

class AssignSoftwareSeat
{
    /**
     * @throws ValidationException
     */
    public function handle(Software $software, Userware $userware, ?string $notes = null): SoftwareAssignment
    {
        if ($userware->organization_id !== $software->organization_id) {
            throw ValidationException::withMessages([
                'userware_id' => __('The selected identity belongs to another organization.'),
            ]);
        }

        if (! $software->hasAvailableSeats()) {
            throw ValidationException::withMessages([
                'userware_id' => __('No seats are available for this license.'),
            ]);
        }

        $existing = SoftwareAssignment::query()
            ->where('software_id', $software->id)
            ->where('userware_id', $userware->id)
            ->first();

        if ($existing !== null) {
            throw ValidationException::withMessages([
                'userware_id' => __('This identity already has a seat for this license.'),
            ]);
        }

        return SoftwareAssignment::create([
            'software_id' => $software->id,
            'userware_id' => $userware->id,
            'assigned_at' => now(),
            'notes' => $notes,
        ]);
    }
}
