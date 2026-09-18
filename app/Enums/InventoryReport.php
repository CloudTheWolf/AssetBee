<?php

namespace App\Enums;

enum InventoryReport: string
{
    case PendingUpdates = 'pending-updates';
    case MissingAntivirus = 'missing-antivirus';
    case UnencryptedDisks = 'unencrypted-disks';
    case StaleInventory = 'stale-inventory';
    case MissingRecoveryKeys = 'missing-recovery-keys';
    case UnassignedDevices = 'unassigned-devices';
    case LaptopsDesktopsDrone = 'laptops-desktops-drone';
    case FullDevicesSoc2 = 'full-devices-soc2';
    case UntrackedDevices = 'untracked-devices';
    case UntrackedServersAndVirtualware = 'untracked-servers-and-virtualware';

    public function title(): string
    {
        return match ($this) {
            self::PendingUpdates => __('Pending updates'),
            self::MissingAntivirus => __('Missing antivirus'),
            self::UnencryptedDisks => __('Unencrypted disks'),
            self::StaleInventory => __('Stale inventory'),
            self::MissingRecoveryKeys => __('Missing recovery keys'),
            self::UnassignedDevices => __('Unassigned devices'),
            self::LaptopsDesktopsDrone => __('Laptops & desktops'),
            self::FullDevicesSoc2 => __('Full device inventory (SOC 2)'),
            self::UntrackedDevices => __('Untracked devices'),
            self::UntrackedServersAndVirtualware => __('Untracked servers & virtualware'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PendingUpdates => __('Devices with outstanding operating system updates.'),
            self::MissingAntivirus => __('Devices without an enabled, up-to-date antivirus product.'),
            self::UnencryptedDisks => __('Devices that reported unencrypted disks.'),
            self::StaleInventory => __('Devices with no inventory, or inventory older than 30 days.'),
            self::MissingRecoveryKeys => __('Encrypted Windows devices without a stored recovery key.'),
            self::UnassignedDevices => __('Laptops and desktops that are not assigned to a person.'),
            self::LaptopsDesktopsDrone => __('All laptop and desktop hardware, with Drone inventory link status.'),
            self::FullDevicesSoc2 => __('Complete hardware and virtualware inventory with device type for SOC 2 evidence.'),
            self::UntrackedDevices => __('Laptops and desktops with no Drone inventory in the last 30 days, including manually added devices.'),
            self::UntrackedServersAndVirtualware => __('Servers and virtualware with no Drone inventory in the last 30 days, including manually added devices.'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PendingUpdates => 'arrow-path',
            self::MissingAntivirus => 'shield-exclamation',
            self::UnencryptedDisks => 'lock-open',
            self::StaleInventory => 'clock',
            self::MissingRecoveryKeys => 'key',
            self::UnassignedDevices => 'user-minus',
            self::LaptopsDesktopsDrone => 'computer-desktop',
            self::FullDevicesSoc2 => 'clipboard-document-list',
            self::UntrackedDevices => 'exclamation-triangle',
            self::UntrackedServersAndVirtualware => 'cloud',
        };
    }

    public function detailHeading(): string
    {
        return match ($this) {
            self::PendingUpdates => __('Updates'),
            self::MissingAntivirus => __('Reason'),
            self::UnencryptedDisks => __('Encryption'),
            self::StaleInventory => __('Last inventory'),
            self::MissingRecoveryKeys => __('Reason'),
            self::UnassignedDevices => __('Status'),
            self::LaptopsDesktopsDrone => __('Drone'),
            self::FullDevicesSoc2 => __('Status'),
            self::UntrackedDevices => __('Last inventory'),
            self::UntrackedServersAndVirtualware => __('Last inventory'),
        };
    }
}
