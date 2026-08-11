<?php

namespace App\Support;

class CloudMode
{
    public static function enabled(): bool
    {
        return (bool) config('app.cloud_hosted', true);
    }

    public static function selfHosted(): bool
    {
        return ! self::enabled();
    }
}
