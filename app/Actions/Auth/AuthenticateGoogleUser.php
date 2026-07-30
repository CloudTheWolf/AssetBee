<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\Registration;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Two\User as GoogleUser;

class AuthenticateGoogleUser
{
    /**
     * Find or create a user from a Google Workspace identity and return them.
     *
     * @throws ValidationException
     */
    public function handle(GoogleUser $googleUser): User
    {
        $this->ensureAllowedWorkspaceDomain($googleUser);

        $user = User::query()->where('google_id', $googleUser->getId())->first();

        if (! $user) {
            $user = User::query()->where('email', $googleUser->getEmail())->first();
        }

        if ($user) {
            $user->forceFill([
                'google_id' => $googleUser->getId(),
                'name' => $googleUser->getName() ?: $user->name,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            return $user->refresh();
        }

        $invitation = Registration::pendingInvitation();

        if (! Registration::isOpen($invitation)) {
            throw ValidationException::withMessages([
                'email' => __('Public registration is closed. Ask an organization owner for an invite.'),
            ]);
        }

        if ($invitation !== null && strtolower((string) $googleUser->getEmail()) !== strtolower($invitation->email)) {
            throw ValidationException::withMessages([
                'email' => __('Sign in with the invited Google account (:email).', [
                    'email' => $invitation->email,
                ]),
            ]);
        }

        return User::create([
            'google_id' => $googleUser->getId(),
            'name' => $googleUser->getName() ?: Str::before((string) $googleUser->getEmail(), '@'),
            'email' => $googleUser->getEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make(Str::password(32)),
        ]);
    }

    /**
     * @throws ValidationException
     */
    private function ensureAllowedWorkspaceDomain(GoogleUser $googleUser): void
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
