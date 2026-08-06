<?php

namespace App\Actions\Assets;

use App\Data\DiscoveredCloudVirtualMachine;
use App\Enums\VirtualwareCategory;
use App\Enums\VirtualwareProvider;
use App\Models\CloudTenant;
use App\Models\Virtualware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ImportCloudVirtualMachines
{
    public function __construct(
        protected DiscoverCloudVirtualMachines $discoverCloudVirtualMachines,
    ) {}

    /**
     * @param  list<string>  $externalIds
     * @return array{created: int, updated: int, virtualwares: Collection<int, Virtualware>}
     *
     * @throws ValidationException
     * @throws RuntimeException
     */
    public function handle(CloudTenant $cloudTenant, array $externalIds, ?string $region = null): array
    {
        $validated = Validator::make(
            [
                'external_ids' => $externalIds,
                'region' => $region,
            ],
            [
                'external_ids' => ['required', 'array', 'min:1'],
                'external_ids.*' => ['required', 'string', 'max:255'],
                'region' => ['nullable', 'string', 'max:64'],
            ],
        )->validate();

        $selectedIds = collect($validated['external_ids'])->unique()->values();

        $discovered = collect($this->discoverCloudVirtualMachines->handle(
            $cloudTenant,
            $validated['region'] ?? null,
        ))
            ->keyBy(fn (DiscoveredCloudVirtualMachine $machine): string => $machine->externalId);

        $missing = $selectedIds->reject(fn (string $id): bool => $discovered->has($id));

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'external_ids' => __('One or more selected instances could not be found in the cloud account.'),
            ]);
        }

        $created = 0;
        $updated = 0;
        $virtualwares = collect();

        foreach ($selectedIds as $externalId) {
            /** @var DiscoveredCloudVirtualMachine $machine */
            $machine = $discovered->get($externalId);

            $virtualware = Virtualware::query()
                ->where('organization_id', $cloudTenant->organization_id)
                ->where('cloud_tenant_id', $cloudTenant->id)
                ->where('external_id', $machine->externalId)
                ->first();

            $attributes = [
                'provider' => $this->mapProvider($cloudTenant),
                'category' => VirtualwareCategory::Vm,
                'host_hardware_id' => null,
                'cloud_tenant_id' => $cloudTenant->id,
                ...$machine->toVirtualwareAttributes(),
            ];

            if ($virtualware === null) {
                $virtualware = $cloudTenant->organization->virtualwares()->create($attributes);
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

    protected function mapProvider(CloudTenant $cloudTenant): VirtualwareProvider
    {
        return match ($cloudTenant->provider->value) {
            'aws' => VirtualwareProvider::Aws,
            'azure' => VirtualwareProvider::Azure,
            'gcp' => VirtualwareProvider::Gcp,
            default => VirtualwareProvider::Other,
        };
    }
}
