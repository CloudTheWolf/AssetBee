<?php

namespace App\Enums;

enum HardwareStatus: string
{
    case Available = 'available';
    case Assigned = 'assigned';
    case Maintenance = 'maintenance';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Available => __('Available'),
            self::Assigned => __('Assigned'),
            self::Maintenance => __('Maintenance'),
            self::Retired => __('Retired'),
        };
    }
}
