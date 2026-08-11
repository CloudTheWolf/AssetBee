<?php

namespace App\Actions\Organizations;

use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Support\CurrentOrganization;
use App\Support\Registration;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptOrganizationInvitation
{
    /**
     * @throws ValidationException
     */
    public function handle(OrganizationInvitation $invitation, User $user): OrganizationInvitation
    {
        if (! $user->isCustomer()) {
            throw ValidationException::withMessages([
                'invitation' => __('System accounts cannot join customer organizations.'),
            ]);
        }

        if (! $invitation->isPending()) {
            throw ValidationException::withMessages([
                'invitation' => __('This invitation is no longer valid.'),
            ]);
        }

        if (strtolower($user->email) !== strtolower($invitation->email)) {
            throw ValidationException::withMessages([
                'invitation' => __('Sign in with :email to accept this invitation.', ['email' => $invitation->email]),
            ]);
        }

        return DB::transaction(function () use ($invitation, $user) {
            $invitation->organization->users()->syncWithoutDetaching([
                $user->id => ['role' => $invitation->role->value],
            ]);

            $invitation->forceFill([
                'accepted_at' => now(),
            ])->save();

            CurrentOrganization::set($invitation->organization, $user);
            Registration::forgetInvitation();

            return $invitation->refresh();
        });
    }
}
