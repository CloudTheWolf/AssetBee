<?php

namespace App\Enums;

enum SoftwareStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Expired => __('Expired'),
            self::Retired => __('Retired'),
        };
    }
}
