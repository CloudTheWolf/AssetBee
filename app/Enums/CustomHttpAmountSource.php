<?php

namespace App\Enums;

enum CustomHttpAmountSource: string
{
    case Response = 'response';
    case Seats = 'seats';

    public function label(): string
    {
        return match ($this) {
            self::Response => __('From response'),
            self::Seats => __('Calculate from seats'),
        };
    }
}
