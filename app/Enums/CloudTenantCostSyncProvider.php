<?php

namespace App\Enums;

enum CloudTenantCostSyncProvider: string
{
    case None = 'none';
    case Native = 'native';
    case CustomHttp = 'custom_http';

    public function label(): string
    {
        return match ($this) {
            self::None => __('None'),
            self::Native => __('Native provider'),
            self::CustomHttp => __('Custom HTTP'),
        };
    }

    public function isConfigured(): bool
    {
        return $this !== self::None;
    }
}
