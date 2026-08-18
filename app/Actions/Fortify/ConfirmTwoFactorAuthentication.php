<?php

namespace App\Actions\Fortify;

use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication as FortifyConfirmTwoFactorAuthentication;

class ConfirmTwoFactorAuthentication extends FortifyConfirmTwoFactorAuthentication
{
    /**
     * Confirm two-factor authentication unless the application is in demo mode.
     *
     * @throws ValidationException
     */
    public function __invoke(mixed $user, mixed $code): void
    {
        if (config('app.demo_mode')) {
            throw ValidationException::withMessages([
                'two_factor' => __('Two-factor authentication cannot be enabled in demo mode.'),
            ]);
        }

        parent::__invoke($user, $code);
    }
}
