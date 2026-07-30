<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InviteOrganizationMember
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Organization $organization, User $inviter, array $input): OrganizationInvitation
    {
        $validated = Validator::make($input, [
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::enum(OrganizationRole::class)],
        ])->validate();

        $email = Str::lower($validated['email']);
        $role = $validated['role'] instanceof OrganizationRole
            ? $validated['role']
            : OrganizationRole::from($validated['role']);

        if ($role === OrganizationRole::Owner) {
            throw ValidationException::withMessages([
                'role' => __('Owners cannot be invited. Transfer ownership separately.'),
            ]);
        }

        $existingMember = $organization->users()
            ->where('email', $email)
            ->exists();

        if ($existingMember) {
            throw ValidationException::withMessages([
                'email' => __('This person is already a member of the organization.'),
            ]);
        }

        $invitation = OrganizationInvitation::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'email' => $email,
            ],
            [
                'invited_by' => $inviter->id,
                'role' => $role,
                'token' => Str::random(64),
                'accepted_at' => null,
                'expires_at' => now()->addDays(7),
            ],
        );

        Notification::route('mail', $email)
            ->notify(new OrganizationInvitationNotification($invitation));

        return $invitation->fresh(['organization', 'inviter']) ?? $invitation;
    }
}
