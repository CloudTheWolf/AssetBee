<?php

namespace App\Enum;


enum HardwareStatus:int
{
    const ACTIVE = 1;
    const INACTIVE = 2;
    const PENDING_DECOM = 3;
    const DECOMMED = 4;
}
