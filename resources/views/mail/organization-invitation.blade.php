<?php

use App\Models\OrganizationInvitation;

/** @var OrganizationInvitation $invitation */
$invitation = $invitation;
?>

<x-mail::message>
# {{ __('Join :organization', ['organization' => $invitation->organization->name]) }}

{{ __(':inviter invited you to join :organization as a :role.', [
    'inviter' => $invitation->inviter->name,
    'organization' => $invitation->organization->name,
    'role' => $invitation->role->label(),
]) }}

<x-mail::button :url="$acceptUrl">
{{ __('Accept invitation') }}
</x-mail::button>

{{ __('This invitation expires on :date.', ['date' => $invitation->expires_at->toFormattedDateString()]) }}

{{ __('If you were not expecting this invitation, you can ignore this email.') }}
</x-mail::message>
