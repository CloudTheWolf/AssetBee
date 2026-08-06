<?php

namespace App\Enums;

enum HardwareOperatingSystem: string
{
    case Windows = 'windows';
    case Windows11 = 'windows_11';
    case Windows10 = 'windows_10';
    case WindowsServer2025 = 'windows_server_2025';
    case WindowsServer2022 = 'windows_server_2022';
    case WindowsServer2019 = 'windows_server_2019';
    case Macos = 'macos';
    case Linux = 'linux';
    case Ios = 'ios';
    case Android = 'android';
    case Chromeos = 'chromeos';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Windows => __('Windows'),
            self::Windows11 => __('Windows 11'),
            self::Windows10 => __('Windows 10'),
            self::WindowsServer2025 => __('Windows Server 2025'),
            self::WindowsServer2022 => __('Windows Server 2022'),
            self::WindowsServer2019 => __('Windows Server 2019'),
            self::Macos => __('macOS'),
            self::Linux => __('Linux'),
            self::Ios => __('iOS'),
            self::Android => __('Android'),
            self::Chromeos => __('ChromeOS'),
            self::Other => __('Other'),
        };
    }

    public function isWindows(): bool
    {
        return match ($this) {
            self::Windows,
            self::Windows11,
            self::Windows10,
            self::WindowsServer2025,
            self::WindowsServer2022,
            self::WindowsServer2019 => true,
            default => false,
        };
    }
}
