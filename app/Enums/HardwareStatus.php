<?php

namespace App\Enums;

enum HardwareStatus: string
{
    case Available = 'available';
    case Assigned = 'assigned';
    case InUse = 'in_use';
    case Maintenance = 'maintenance';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Available => __('Available'),
            self::Assigned => __('Assigned'),
            self::InUse => __('In Use'),
            self::Maintenance => __('Maintenance'),
            self::Retired => __('Retired'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Available => 'green',
            self::Assigned => 'blue',
            self::InUse => 'indigo',
            self::Maintenance => 'amber',
            self::Retired => 'zinc',
        };
    }
}
