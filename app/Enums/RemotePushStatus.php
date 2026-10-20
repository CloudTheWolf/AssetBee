<?php

namespace App\Enums;

enum RemotePushStatus: string
{
    case Open = 'open';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'amber',
            self::Completed => 'green',
            self::Cancelled => 'zinc',
        };
    }
}
