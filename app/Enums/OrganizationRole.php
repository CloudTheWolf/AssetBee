<?php

namespace App\Enums;

enum OrganizationRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    public function canManageAssets(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }

    public function canManageOrganization(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }

    public function canInviteMembers(): bool
    {
        return $this === self::Owner;
    }

    public function label(): string
    {
        return match ($this) {
            self::Owner => __('Owner'),
            self::Admin => __('Admin'),
            self::Member => __('Member'),
        };
    }
}
