<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class UnlinkGoogleAccount
{
    /**
     * Remove the Google identity from the user account.
     */
    public function handle(User $user): void
    {
        if (config('app.demo_mode')) {
            throw ValidationException::withMessages([
                'google' => __('Connected accounts cannot be changed in demo mode.'),
            ]);
        }

        if ($user->google_id === null) {
            return;
        }

        $user->forceFill([
            'google_id' => null,
        ])->save();
    }
}
