<?php

namespace App\Support;

use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Session;

class Registration
{
    public const INVITATION_SESSION_KEY = 'pending_organization_invitation';

    public static function selfHosted(): bool
    {
        return CloudMode::selfHosted();
    }

    /**
     * Public signup is available unless this is a self-hosted install that already has a user.
     * Pending invitations still allow invited people to create an account.
     */
    public static function isOpen(?OrganizationInvitation $invitation = null): bool
    {
        if ($invitation?->isPending()) {
            return true;
        }

        if (! self::selfHosted()) {
            return true;
        }

        return ! User::query()->exists();
    }

    public static function rememberInvitation(OrganizationInvitation $invitation): void
    {
        Session::put(self::INVITATION_SESSION_KEY, $invitation->token);
    }

    public static function pendingInvitation(): ?OrganizationInvitation
    {
        $token = Session::get(self::INVITATION_SESSION_KEY);

        if (! is_string($token) || $token === '') {
            return null;
        }

        return OrganizationInvitation::query()
            ->where('token', $token)
            ->pending()
            ->first();
    }

    public static function forgetInvitation(): void
    {
        Session::forget(self::INVITATION_SESSION_KEY);
    }
}
