<?php

namespace App\Actions\Fortify;

use App\Actions\Organizations\AcceptOrganizationInvitation;
use App\Actions\Organizations\EnsureUserOrganization;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Support\Registration;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $invitation = Registration::pendingInvitation();

        if (! Registration::isOpen($invitation)) {
            throw ValidationException::withMessages([
                'email' => __('Public registration is closed. Ask an organization owner for an invite.'),
            ]);
        }

        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        if ($invitation !== null && strtolower($input['email']) !== strtolower($invitation->email)) {
            throw ValidationException::withMessages([
                'email' => __('Use the invited email address (:email) to create your account.', [
                    'email' => $invitation->email,
                ]),
            ]);
        }

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        if ($invitation !== null) {
            app(AcceptOrganizationInvitation::class)->handle($invitation, $user);
        } else {
            app(EnsureUserOrganization::class)->handle($user);
        }

        return $user;
    }
}
