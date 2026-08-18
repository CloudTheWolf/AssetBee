<?php

namespace App\Support;

use App\Enums\BitLockerStatus;
use App\Enums\HardwareStatus;
use App\Enums\InventoryReport;
use App\Enums\VirtualwareStatus;
use App\Models\Hardware;
use App\Models\Organization;
use App\Models\Virtualware;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class OrganizationInventoryReports
{
    public const STALE_AFTER_DAYS = 30;

    /**
     * @return list<array{report: InventoryReport, count: int}>
     */
    public function catalog(Organization $organization): array
    {
        $devices = $this->devices($organization);

        return array_map(
            fn (InventoryReport $report): array => [
                'report' => $report,
                'count' => $this->matching($devices, $report)->count(),
            ],
            InventoryReport::cases(),
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(Organization $organization, InventoryReport $report): Collection
    {
        return $this->matching($this->devices($organization), $report)
            ->map(fn (array $device): array => $this->present($device, $report))
            ->values();
    }

    /**
     * @return list<string>
     */
    public function pdfLines(Organization $organization, InventoryReport $report): array
    {
        $rows = $this->rows($organization, $report);

        $lines = [
            $report->description(),
            '',
            __('Organization').': '.$organization->name,
            __('Generated').': '.now()->timezone((string) config('app.timezone'))->toDayDateTimeString(),
            __('Devices').': '.$rows->count(),
            '',
        ];

        if ($rows->isEmpty()) {
            $lines[] = __('No devices match this report.');

            return $lines;
        }

        $lines[] = __('Device').' | '.__('Type').' | '.__('Assigned to').' | '.$report->detailHeading();
        $lines[] = '';

        foreach ($rows as $row) {
            $type = $row['asset_type'] === 'hardware' ? __('Hardware') : __('Virtualware');
            $assigned = is_string($row['assigned_to']) && $row['assigned_to'] !== ''
                ? $row['assigned_to']
                : '—';

            $lines[] = $row['name'].' | '.$type.' | '.$assigned.' | '.$row['detail'];

            if (is_string($row['serial_number']) && $row['serial_number'] !== '') {
                $lines[] = '  '.$row['serial_number'];
            }
        }

        return $lines;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function devices(Organization $organization): Collection
    {
        $hardware = $organization->hardwares()
            ->with('assignedUserware')
            ->orderBy('name')
            ->get()
            ->map(fn (Hardware $hardware): array => $this->hardwareDevice($hardware));

        $virtualware = $organization->virtualwares()
            ->with('assignedUserware')
            ->orderBy('name')
            ->get()
            ->map(fn (Virtualware $virtualware): array => $this->virtualwareDevice($virtualware));

        return $hardware->concat($virtualware)->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function hardwareDevice(Hardware $hardware): array
    {
        $payload = is_array($hardware->inventory_payload) ? $hardware->inventory_payload : [];

        return [
            'asset_type' => 'hardware',
            'id' => $hardware->id,
            'name' => $hardware->name,
            'serial_number' => $hardware->serial_number,
            'assigned_to' => $hardware->assignedUserware?->name,
            'collected_at' => $hardware->inventory_collected_at,
            'url' => route('assets.hardware.show', $hardware),
            'status_label' => $hardware->status->label(),
            'payload' => $payload,
            'is_windows' => $hardware->operating_system?->isWindows() ?? false,
            'bitlocker_status' => $hardware->bitlocker_status,
            'recovery_key_stored' => filled($hardware->bitlocker_recovery_key),
            'is_unassigned' => $hardware->status === HardwareStatus::Available
                && $hardware->assigned_userware_id === null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function virtualwareDevice(Virtualware $virtualware): array
    {
        $payload = is_array($virtualware->inventory_payload) ? $virtualware->inventory_payload : [];

        return [
            'asset_type' => 'virtualware',
            'id' => $virtualware->id,
            'name' => $virtualware->name,
            'serial_number' => $virtualware->serial_number,
            'assigned_to' => $virtualware->assignedUserware?->name,
            'collected_at' => $virtualware->inventory_collected_at,
            'url' => route('assets.virtualware.show', $virtualware),
            'status_label' => $virtualware->status->label(),
            'payload' => $payload,
            'is_windows' => false,
            'bitlocker_status' => null,
            'recovery_key_stored' => false,
            'is_unassigned' => $virtualware->status !== VirtualwareStatus::Decommissioned
                && $virtualware->assigned_userware_id === null,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $devices
     * @return Collection<int, array<string, mixed>>
     */
    private function matching(Collection $devices, InventoryReport $report): Collection
    {
        return $devices->filter(fn (array $device): bool => match ($report) {
            InventoryReport::PendingUpdates => $this->hasPendingUpdates($device),
            InventoryReport::MissingAntivirus => $this->isMissingAntivirus($device),
            InventoryReport::UnencryptedDisks => $this->isUnencrypted($device),
            InventoryReport::StaleInventory => $this->isStale($device),
            InventoryReport::MissingRecoveryKeys => $this->isMissingRecoveryKey($device),
            InventoryReport::UnassignedDevices => (bool) $device['is_unassigned'],
        })->values();
    }

    /**
     * @param  array<string, mixed>  $device
     * @return array<string, mixed>
     */
    private function present(array $device, InventoryReport $report): array
    {
        return [
            'asset_type' => $device['asset_type'],
            'id' => $device['id'],
            'name' => $device['name'],
            'serial_number' => $device['serial_number'],
            'assigned_to' => $device['assigned_to'],
            'collected_at' => $device['collected_at'],
            'url' => $device['url'],
            'detail' => $this->detail($device, $report),
        ];
    }

    /**
     * @param  array<string, mixed>  $device
     */
    private function detail(array $device, InventoryReport $report): string
    {
        return match ($report) {
            InventoryReport::PendingUpdates => $this->pendingUpdatesDetail($device),
            InventoryReport::MissingAntivirus => $this->missingAntivirusReason($device),
            InventoryReport::UnencryptedDisks => $this->encryptionDetail($device),
            InventoryReport::StaleInventory => $this->staleDetail($device),
            InventoryReport::MissingRecoveryKeys => __('No recovery key stored.'),
            InventoryReport::UnassignedDevices => (string) $device['status_label'],
        };
    }

    /**
     * @param  array<string, mixed>  $device
     */
    private function hasPendingUpdates(array $device): bool
    {
        return $this->availableUpdateCount($device) > 0;
    }

    /**
     * @param  array<string, mixed>  $device
     */
    private function availableUpdateCount(array $device): int
    {
        $updates = InventoryProbe::value($device['payload'], 'updates');
        $available = is_array(data_get($updates, 'available')) ? data_get($updates, 'available') : [];

        return count($available);
    }

    /**
     * @param  array<string, mixed>  $device
     */
    private function pendingUpdatesDetail(array $device): string
    {
        $updates = InventoryProbe::value($device['payload'], 'updates');
        $available = is_array(data_get($updates, 'available')) ? data_get($updates, 'available') : [];
        $titles = collect($available)
            ->map(function (mixed $update): ?string {
                if (! is_array($update)) {
                    return null;
                }

                foreach (['title', 'kbArticle', 'id'] as $key) {
                    $value = $update[$key] ?? null;

                    if (is_string($value) && trim($value) !== '') {
                        return trim($value);
                    }
                }

                return null;
            })
            ->filter()
            ->values();

        $shown = $titles->take(3);
        $detail = $shown->implode(', ');

        if ($titles->count() > 3) {
            $detail .= '…';
        }

        return $detail !== ''
            ? $titles->count().': '.$detail
            : (string) $titles->count();
    }

    /**
     * @param  array<string, mixed>  $device
     */
    private function isMissingAntivirus(array $device): bool
    {
        if ($device['collected_at'] === null) {
            return false;
        }

        if (InventoryProbe::status($device['payload'], 'antivirus') !== 'available') {
            return true;
        }

        $products = InventoryProbe::list($device['payload'], 'antivirus');
        $protected = collect($products)->contains(
            fn (array $product): bool => ($product['enabled'] ?? null) === true
                && ($product['upToDate'] ?? null) === true,
        );

        return ! $protected;
    }

    /**
     * @param  array<string, mixed>  $device
     */
    private function missingAntivirusReason(array $device): string
    {
        if (InventoryProbe::status($device['payload'], 'antivirus') !== 'available') {
            return __('No antivirus inventory.');
        }

        $products = InventoryProbe::list($device['payload'], 'antivirus');

        if ($products === []) {
            return __('No antivirus product reported.');
        }

        $enabled = collect($products)->first(
            fn (array $product): bool => ($product['enabled'] ?? null) === true,
        );

        if ($enabled === null) {
            return __('Antivirus is disabled.');
        }

        return __('Antivirus is out of date.');
    }

    /**
     * @param  array<string, mixed>  $device
     */
    private function isUnencrypted(array $device): bool
    {
        if ($device['bitlocker_status'] === BitLockerStatus::Disabled) {
            return true;
        }

        return $this->encryptionStatus($device) === 'unencrypted';
    }

    /**
     * @param  array<string, mixed>  $device
     */
    private function encryptionStatus(array $device): string
    {
        if (InventoryProbe::status($device['payload'], 'diskEncryption') !== 'available') {
            return 'unknown';
        }

        $encryptedVolumes = collect(InventoryProbe::list($device['payload'], 'diskEncryption'))
            ->filter(fn (array $volume): bool => str_contains(strtolower((string) ($volume['state'] ?? '')), 'encrypted'))
            ->count();

        return $encryptedVolumes > 0 ? 'encrypted' : 'unencrypted';
    }

    /**
     * @param  array<string, mixed>  $device
     */
    private function encryptionDetail(array $device): string
    {
        if ($device['bitlocker_status'] === BitLockerStatus::Disabled) {
            return __('BitLocker is disabled.');
        }

        return __('No encrypted volumes reported.');
    }

    /**
     * @param  array<string, mixed>  $device
     */
    private function isStale(array $device): bool
    {
        $collectedAt = $device['collected_at'];

        if ($collectedAt === null) {
            return true;
        }

        return $collectedAt->lt(now()->subDays(self::STALE_AFTER_DAYS));
    }

    /**
     * @param  array<string, mixed>  $device
     */
    private function staleDetail(array $device): string
    {
        $collectedAt = $device['collected_at'];

        if (! $collectedAt instanceof CarbonInterface) {
            return __('Never collected.');
        }

        return __('Last collected :when.', ['when' => $collectedAt->diffForHumans()]);
    }

    /**
     * @param  array<string, mixed>  $device
     */
    private function isMissingRecoveryKey(array $device): bool
    {
        if ($device['asset_type'] !== 'hardware' || $device['recovery_key_stored']) {
            return false;
        }

        if ($device['bitlocker_status'] === BitLockerStatus::Enabled) {
            return true;
        }

        return $this->encryptionStatus($device) === 'encrypted';
    }
}
