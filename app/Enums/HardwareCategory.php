<?php

namespace App\Enums;

enum HardwareCategory: string
{
    case Laptop = 'laptop';
    case Desktop = 'desktop';
    case Server = 'server';
    case Mobile = 'mobile';
    case Monitor = 'monitor';
    case Network = 'network';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Laptop => __('Laptop'),
            self::Desktop => __('Desktop'),
            self::Server => __('Server'),
            self::Mobile => __('Mobile'),
            self::Monitor => __('Monitor'),
            self::Network => __('Network'),
            self::Other => __('Other'),
        };
    }

    public function hasComputeSpecs(): bool
    {
        return match ($this) {
            self::Laptop, self::Desktop, self::Server, self::Mobile => true,
            default => false,
        };
    }

    public function canBeVmHost(): bool
    {
        return $this === self::Server;
    }
}
