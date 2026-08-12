<?php

namespace App\Actions\Auth;

use App\Actions\Auth\Concerns\ValidatesGoogleWorkspaceDomain;
use App\Enums\UserAccountType;
use App\Models\User;
use App\Support\Registration;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Two\User as GoogleUser;

class AuthenticateGoogleUser
{
    use ValidatesGoogleWorkspaceDomain;

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

        $user = new User([
            'google_id' => $googleUser->getId(),
            'name' => $googleUser->getName() ?: Str::before((string) $googleUser->getEmail(), '@'),
            'email' => $googleUser->getEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make(Str::password(32)),
        ]);
        $user->account_type = UserAccountType::Customer;
        $user->save();

        return $user;
    }
}
