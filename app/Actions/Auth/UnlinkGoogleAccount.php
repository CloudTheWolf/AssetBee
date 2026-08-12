<?php

namespace App\Actions\Auth;

use App\Models\User;

class UnlinkGoogleAccount
{
    /**
     * Remove the Google identity from the user account.
     */
    public function handle(User $user): void
    {
        if ($user->google_id === null) {
            return;
        }

        $user->forceFill([
            'google_id' => null,
        ])->save();
    }
}
