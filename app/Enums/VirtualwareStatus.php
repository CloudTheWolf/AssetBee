<?php

namespace App\Enums;

enum VirtualwareStatus: string
{
    case Running = 'running';
    case Stopped = 'stopped';
    case Decommissioned = 'decommissioned';

    public function label(): string
    {
        return match ($this) {
            self::Running => __('Running'),
            self::Stopped => __('Stopped'),
            self::Decommissioned => __('Decommissioned'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Running => 'green',
            self::Stopped => 'amber',
            self::Decommissioned => 'zinc',
        };
    }
}
