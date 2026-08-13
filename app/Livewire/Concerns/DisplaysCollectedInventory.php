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
