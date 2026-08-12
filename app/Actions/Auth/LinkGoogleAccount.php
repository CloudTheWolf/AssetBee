<?php

namespace App\Actions\Auth;

use App\Actions\Auth\Concerns\ValidatesGoogleWorkspaceDomain;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Two\User as GoogleUser;

class LinkGoogleAccount
{
    use ValidatesGoogleWorkspaceDomain;

    /**
     * Attach a Google identity to an existing authenticated user.
     *
     * @throws ValidationException
     */
    public function handle(User $user, GoogleUser $googleUser): User
    {
        $this->ensureAllowedWorkspaceDomain($googleUser);

        $googleId = (string) $googleUser->getId();
        $googleEmail = Str::lower((string) $googleUser->getEmail());

        if ($googleEmail !== Str::lower($user->email)) {
            throw ValidationException::withMessages([
                'google' => __('Use the Google account that matches your email address (:email).', [
                    'email' => $user->email,
                ]),
            ]);
        }

        $alreadyLinked = User::query()
            ->where('google_id', $googleId)
            ->whereKeyNot($user->id)
            ->exists();

        if ($alreadyLinked) {
            throw ValidationException::withMessages([
                'google' => __('This Google account is already linked to another user.'),
            ]);
        }

        $user->forceFill([
            'google_id' => $googleId,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        return $user->refresh();
    }
}
