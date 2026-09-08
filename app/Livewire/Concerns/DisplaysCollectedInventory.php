<?php

namespace App\Livewire\Concerns;

use App\Support\SbomListing;
use Livewire\Attributes\Computed;

/**
 * @property-read array<string, mixed>|null $inventory
 * @property string $sbomSearch
 */
trait DisplaysCollectedInventory
{
    public bool $showRecoveryKeyModal = false;

    public string $revealedRecoveryVolume = '';

    /**
     * @var list<array{identifier: string, key: string}>
     */
    public array $revealedRecoveryKeys = [];

    /**
     * @return array<string, mixed>|null
     */
    protected function inventoryProbe(string $key): ?array
    {
        $probe = data_get($this->inventory, $key);

        return is_array($probe) ? $probe : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function inventoryList(string $key): array
    {
        $probe = $this->inventoryProbe($key);

        if (($probe['status'] ?? null) !== 'available' || ! is_array($probe['value'] ?? null)) {
            return [];
        }

        return array_values(array_filter(
            $probe['value'],
            fn (mixed $item): bool => is_array($item),
        ));
    }

    /**
     * @return list<array{target: array<string, mixed>, components: list<array<string, mixed>>, matchingCount: int}>
     */
    #[Computed]
    public function filteredSbomTargets(): array
    {
        return SbomListing::filteredTargets($this->inventoryProbe('sbom'), $this->sbomSearch);
    }

    public function revealRecoveryKey(int $index): void
    {
        $volume = $this->inventoryList('diskEncryption')[$index] ?? null;

        if (! is_array($volume)) {
            return;
        }

        $keys = $this->recoveryKeysForVolume($volume);

        if ($keys === []) {
            return;
        }

        $this->revealedRecoveryKeys = $keys;
        $this->revealedRecoveryVolume = is_string($volume['volume'] ?? null) ? $volume['volume'] : '';
        $this->showRecoveryKeyModal = true;
    }

    public function closeRecoveryKeyModal(): void
    {
        $this->showRecoveryKeyModal = false;
        $this->revealedRecoveryVolume = '';
        $this->revealedRecoveryKeys = [];
    }

    /**
     * @param  array<string, mixed>  $volume
     * @return list<array{identifier: string, key: string}>
     */
    protected function recoveryKeysForVolume(array $volume): array
    {
        $keys = [];

        foreach ($volume['keyProtectors'] ?? [] as $protector) {
            if (! is_array($protector)) {
                continue;
            }

            $recoveryKey = $protector['recoveryKey'] ?? null;
            $identifier = $protector['keyProtectorId'] ?? null;

            if (! is_string($recoveryKey) || $recoveryKey === '') {
                continue;
            }

            if (! is_string($identifier) || $identifier === '') {
                continue;
            }

            $keys[] = [
                'identifier' => $this->formatBitLockerIdentifier($identifier),
                'key' => $recoveryKey,
            ];
        }

        return $keys;
    }

    protected function formatBitLockerIdentifier(string $identifier): string
    {
        return trim($identifier, '{}');
    }

    protected function formatBytes(mixed $bytes): string
    {
        if (! is_int($bytes) || $bytes < 0) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return round($value, $unit === 0 ? 0 : 1).' '.$units[$unit];
    }
}
