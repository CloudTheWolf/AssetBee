<?php

namespace App\Enums;

enum SoftwareBillingInterval: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => __('Monthly'),
            self::Quarterly => __('Quarterly'),
            self::Yearly => __('Yearly'),
        };
    }

    public function monthsPerPeriod(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Quarterly => 3,
            self::Yearly => 12,
        };
    }
}
