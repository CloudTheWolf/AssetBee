<?php

namespace App\Actions\Assets;

use App\Enums\CloudTenantCostSyncProvider;
use App\Enums\SoftwareCostSyncProvider;
use App\Models\CloudTenant;
use App\Models\Organization;
use App\Models\Software;
use Throwable;

class SyncOrganizationAssetCosts
{
    public function __construct(
        protected SyncAssetCosts $syncAssetCosts,
    ) {}

    /**
     * @return array{organizations: int, assets: int, synced: int, failed: int, errors: list<string>}
     */
    public function handle(): array
    {
        $organizations = Organization::query()
            ->where('cost_sync_enabled', true)
            ->pluck('id');

        $synced = 0;
        $failed = 0;
        $assets = 0;
        /** @var list<string> $errors */
        $errors = [];

        $software = Software::query()
            ->whereIn('organization_id', $organizations)
            ->where('cost_sync_provider', '!=', SoftwareCostSyncProvider::None->value)
            ->get();

        foreach ($software as $item) {
            $assets++;
            try {
                $this->syncAssetCosts->handle($item);
                $synced++;
            } catch (Throwable $exception) {
                $failed++;
                $errors[] = __('Software #:id (:name): :message', [
                    'id' => $item->id,
                    'name' => $item->name,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $tenants = CloudTenant::query()
            ->whereIn('organization_id', $organizations)
            ->where('cost_sync_provider', '!=', CloudTenantCostSyncProvider::None->value)
            ->get();

        foreach ($tenants as $tenant) {
            $assets++;
            try {
                $this->syncAssetCosts->handle($tenant);
                $synced++;
            } catch (Throwable $exception) {
                $failed++;
                $errors[] = __('Cloud tenant #:id (:name): :message', [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return [
            'organizations' => $organizations->count(),
            'assets' => $assets,
            'synced' => $synced,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }
}
