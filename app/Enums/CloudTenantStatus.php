<?php

namespace App\Enums;

enum CloudTenantStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Suspended => __('Suspended'),
            self::Closed => __('Closed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Suspended => 'amber',
            self::Closed => 'zinc',
        };
    }
}
