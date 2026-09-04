<?php

namespace App\Actions\Assets;

use App\Data\DiscoveredProxmoxGuest;
use App\Enums\VirtualwareProvider;
use App\Models\Hardware;
use App\Models\Virtualware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ImportProxmoxGuests
{
    public function __construct(
        protected DiscoverProxmoxGuests $discoverProxmoxGuests,
        protected ResolveProxmoxHostHardware $resolveProxmoxHostHardware,
    ) {}

    /**
     * @param  list<string>  $externalIds
     * @return array{created: int, updated: int, virtualwares: Collection<int, Virtualware>}
     *
     * @throws ValidationException
     * @throws RuntimeException
     */
    public function handle(Hardware $hardware, array $externalIds): array
    {
        $validated = Validator::make(
            ['external_ids' => $externalIds],
            [
                'external_ids' => ['required', 'array', 'min:1'],
                'external_ids.*' => ['required', 'string', 'max:255'],
            ],
        )->validate();

        /** @var list<string> $externalIdInput */
        $externalIdInput = array_values(array_filter(
            $validated['external_ids'],
            fn (mixed $id): bool => is_string($id) && $id !== '',
        ));

        $selectedIds = collect($externalIdInput)->unique()->values();

        $discovered = collect($this->discoverProxmoxGuests->handle($hardware))
            ->keyBy(fn (DiscoveredProxmoxGuest $guest): string => $guest->externalId);

        $missing = $selectedIds->reject(fn (string $id): bool => $discovered->has($id));

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'external_ids' => __('One or more selected guests could not be found on the Proxmox host.'),
            ]);
        }

        $created = 0;
        $updated = 0;
        $virtualwares = collect();

        foreach ($selectedIds as $externalId) {
            /** @var DiscoveredProxmoxGuest $guest */
            $guest = $discovered->get($externalId);
            $host = $this->resolveProxmoxHostHardware->handle($hardware, $guest);

            $virtualware = $this->findExistingVirtualware($hardware, $guest);

            $attributes = [
                'provider' => VirtualwareProvider::Proxmox,
                'host_hardware_id' => $host->id,
                'cloud_tenant_id' => null,
                ...$guest->toVirtualwareAttributes(),
            ];

            if ($virtualware === null) {
                $virtualware = $hardware->organization->virtualwares()->create($attributes);
                $created++;
            } else {
                $virtualware->update($attributes);
                $updated++;
            }

            $virtualwares->push($virtualware->refresh());
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'virtualwares' => $virtualwares,
        ];
    }

    protected function findExistingVirtualware(Hardware $hardware, DiscoveredProxmoxGuest $guest): ?Virtualware
    {
        $byExternalId = Virtualware::query()
            ->where('organization_id', $hardware->organization_id)
            ->where('provider', VirtualwareProvider::Proxmox)
            ->where('external_id', $guest->externalId)
            ->first();

        if ($byExternalId !== null) {
            return $byExternalId;
        }

        return Virtualware::query()
            ->where('organization_id', $hardware->organization_id)
            ->where('name', $guest->name)
            ->orderBy('id')
            ->first();
    }
}
