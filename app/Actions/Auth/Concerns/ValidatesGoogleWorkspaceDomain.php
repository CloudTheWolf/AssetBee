<?php

namespace App\Actions\Auth\Concerns;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Two\User as GoogleUser;

trait ValidatesGoogleWorkspaceDomain
{
    /**
     * @throws ValidationException
     */
    protected function ensureAllowedWorkspaceDomain(GoogleUser $googleUser): void
    {
        $email = $googleUser->getEmail();

        if (! $email) {
            throw ValidationException::withMessages([
                'email' => __('Unable to retrieve an email address from Google.'),
            ]);
        }

        $allowedDomains = config('services.google.hosted_domains', []);

        if ($allowedDomains === []) {
            return;
        }

        $emailDomain = Str::lower(Str::after($email, '@'));

        if (! in_array($emailDomain, $allowedDomains, true)) {
            throw ValidationException::withMessages([
                'email' => __('Your Google account is not part of an allowed Workspace domain.'),
            ]);
        }

        $hostedDomain = $googleUser->user['hd'] ?? null;

        if (is_string($hostedDomain) && $hostedDomain !== '' && ! in_array(Str::lower($hostedDomain), $allowedDomains, true)) {
            throw ValidationException::withMessages([
                'email' => __('Your Google account is not part of an allowed Workspace domain.'),
            ]);
        }
    }
}
