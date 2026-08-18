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

    public function title(): string
    {
        return match ($this) {
            self::PendingUpdates => __('Pending updates'),
            self::MissingAntivirus => __('Missing antivirus'),
            self::UnencryptedDisks => __('Unencrypted disks'),
            self::StaleInventory => __('Stale inventory'),
            self::MissingRecoveryKeys => __('Missing recovery keys'),
            self::UnassignedDevices => __('Unassigned devices'),
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
            self::UnassignedDevices => __('Hardware and virtualware that are not assigned to a person.'),
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
        };
    }
}
