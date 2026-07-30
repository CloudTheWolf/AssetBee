<?php

namespace App\Enums;

enum BitLockerStatus: string
{
    case Enabled = 'enabled';
    case Disabled = 'disabled';
    case Unknown = 'unknown';
    case NotApplicable = 'not_applicable';

    public function label(): string
    {
        return match ($this) {
            self::Enabled => __('Enabled'),
            self::Disabled => __('Disabled'),
            self::Unknown => __('Unknown'),
            self::NotApplicable => __('Not applicable'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Enabled => 'green',
            self::Disabled => 'red',
            self::Unknown => 'amber',
            self::NotApplicable => 'zinc',
        };
    }
}
