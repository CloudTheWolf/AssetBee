<?php

namespace App\Support;

class CloudMode
{
    public static function enabled(): bool
    {
        return ! self::selfHosted();
    }

    public static function selfHosted(): bool
    {
        return (bool) config('app.self_hosted', false);
    }
}
