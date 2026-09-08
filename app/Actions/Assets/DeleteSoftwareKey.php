<?php

namespace App\Actions\Assets;

use App\Models\SoftwareKey;
use Illuminate\Validation\ValidationException;

class DeleteSoftwareKey
{
    /**
     * @throws ValidationException
     */
    public function handle(SoftwareKey $key): void
    {
        if ($key->isAssigned()) {
            throw ValidationException::withMessages([
                'key' => __('Assigned license keys cannot be deleted. Unassign the key first.'),
            ]);
        }

        $key->delete();
    }
}
