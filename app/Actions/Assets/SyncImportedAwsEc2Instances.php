<?php

namespace App\Actions\Assets;

use App\Enums\CloudTenantProvider;
use App\Enums\VirtualwareProvider;
use App\Models\CloudTenant;
use App\Models\Virtualware;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncImportedAwsEc2Instances
{
    public function __construct(
        protected ImportCloudVirtualMachines $importCloudVirtualMachines,
    ) {}

    /**
     * Refresh already-imported AWS EC2 virtualware across credentialed tenants.
     *
     * @return array{tenants: int, updated: int, created: int, failed: int, errors: list<string>}
     */
    public function handle(): array
    {
        $tenants = CloudTenant::query()
            ->where('provider', CloudTenantProvider::Aws)
            ->whereHas('organization', fn ($query) => $query->where('virtualware_sync_enabled', true))
            ->get()
            ->filter(fn (CloudTenant $tenant): bool => $tenant->hasCredentials());

        $updated = 0;
        $created = 0;
        $failed = 0;
        /** @var list<string> $errors */
        $errors = [];

        foreach ($tenants as $tenant) {
            try {
                $result = $this->syncTenant($tenant);
                $updated += $result['updated'];
                $created += $result['created'];
            } catch (Throwable $exception) {
                $failed++;
                $message = __('AWS tenant :name (#:id): :error', [
                    'name' => $tenant->name,
                    'id' => $tenant->id,
                    'error' => $exception->getMessage(),
                ]);
                $errors[] = $message;
                Log::warning($message, ['exception' => $exception]);
            }
        }

        return [
            'tenants' => $tenants->count(),
            'updated' => $updated,
            'created' => $created,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * @return array{created: int, updated: int}
     */
    protected function syncTenant(CloudTenant $tenant): array
    {
        $imported = Virtualware::query()
            ->where('organization_id', $tenant->organization_id)
            ->where('cloud_tenant_id', $tenant->id)
            ->where('provider', VirtualwareProvider::Aws)
            ->whereNotNull('external_id')
            ->get(['id', 'external_id', 'region']);

        if ($imported->isEmpty()) {
            return ['created' => 0, 'updated' => 0];
        }

        $defaultRegion = (string) ($tenant->credentials['region'] ?? 'us-east-1');

        $byRegion = $imported->groupBy(
            fn (Virtualware $virtualware): string => filled($virtualware->region)
                ? (string) $virtualware->region
                : $defaultRegion,
        );

        $created = 0;
        $updated = 0;

        foreach ($byRegion as $region => $virtualwares) {
            /** @var list<string> $externalIds */
            $externalIds = $virtualwares
                ->pluck('external_id')
                ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
                ->unique()
                ->values()
                ->all();

            if ($externalIds === []) {
                continue;
            }

            $result = $this->importCloudVirtualMachines->handle($tenant, $externalIds, $region);
            $created += $result['created'];
            $updated += $result['updated'];
        }

        return [
            'created' => $created,
            'updated' => $updated,
        ];
    }
}
