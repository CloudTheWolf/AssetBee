<?php

namespace App\Enums;

enum UserAccountType: string
{
    case Customer = 'customer';
    case System = 'system';
}
