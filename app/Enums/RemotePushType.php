<?php

namespace App\Enums;

enum RemotePushType: string
{
    case BitlockerStatus = 'bitlocker_status';
    case WindowsUpdateStatus = 'windows_update_status';
    case InstallPackage = 'install_package';

    public function label(): string
    {
        return match ($this) {
            self::BitlockerStatus => __('Get BitLocker status'),
            self::WindowsUpdateStatus => __('Get Windows Update status'),
            self::InstallPackage => __('Install package'),
        };
    }

    public function requiresPackage(): bool
    {
        return $this === self::InstallPackage;
    }

    public function requiresUserConsent(): bool
    {
        return $this === self::InstallPackage;
    }
}
