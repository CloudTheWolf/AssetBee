<?php

namespace App\Enums;

enum SoftwareSeatManagerType: string
{
    case Userware = 'userware';
    case Department = 'department';

    public function label(): string
    {
        return match ($this) {
            self::Userware => __('User'),
            self::Department => __('Department'),
        };
    }
}
