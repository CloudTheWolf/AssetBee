<?php

namespace App\Enums;

enum SoftwareLicenseType: string
{
    case Seat = 'seat';
    case Site = 'site';
    case Subscription = 'subscription';
    case Key = 'key';

    public function label(): string
    {
        return match ($this) {
            self::Seat => __('Seat'),
            self::Site => __('Site'),
            self::Subscription => __('Subscription'),
            self::Key => __('Key'),
        };
    }
}
