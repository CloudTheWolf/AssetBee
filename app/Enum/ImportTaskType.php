<?php

namespace App\Enum;


enum ImportTaskType:string
{
    const HARDWARE = "hardware";
    const SOFTWARE = "software";
    const VIRTUALWARE = "virtualware";
    const USERWARE = "userware";
}
