<?php

namespace App\Enums;

enum RemotePackageSource: string
{
    case Msi = 'msi';
    case Winget = 'winget';
    case Choco = 'choco';

    public function label(): string
    {
        return match ($this) {
            self::Msi => __('MSI'),
            self::Winget => __('Winget'),
            self::Choco => __('Chocolatey'),
        };
    }
}
