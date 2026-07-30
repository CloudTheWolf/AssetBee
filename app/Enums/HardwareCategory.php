<?php

namespace App\Enums;

enum HardwareCategory: string
{
    case Laptop = 'laptop';
    case Desktop = 'desktop';
    case Monitor = 'monitor';
    case Mobile = 'mobile';
    case Network = 'network';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Laptop => __('Laptop'),
            self::Desktop => __('Desktop'),
            self::Monitor => __('Monitor'),
            self::Mobile => __('Mobile'),
            self::Network => __('Network'),
            self::Other => __('Other'),
        };
    }
}
