<?php

namespace App\Actions\Assets;

use App\Models\AssetDocument;
use App\Models\CloudTenant;
use App\Models\CostSnapshot;
use App\Models\Hardware;
use App\Models\Software;
use App\Models\SoftwareAssignment;
use App\Models\Userware;
use App\Models\UserwareAccount;
use App\Models\Virtualware;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

class PurgeSoftDeletedAssets
{
    /**
     * Release allocations tied to soft-deleted assets, then permanently remove
     * soft-deleted rows older than the retention window.
     *
     * @return array{
     *     allocations_released: int,
     *     purged: array{software: int, userware: int, hardware: int, virtualware: int, cloud_tenants: int}
     * }
     */
    public function handle(?int $retentionDays = null): array
    {
        $retentionDays ??= (int) config('assets.soft_delete_retention_days', 30);
        $cutoff = now()->subDays(max(0, $retentionDays));

        return [
            'allocations_released' => $this->releaseAllocations(),
            'purged' => [
                'software' => $this->purgeSoftwares($cutoff),
                'userware' => $this->purgeUserwares($cutoff),
                'hardware' => $this->purgeHardwares($cutoff),
                'virtualware' => $this->purgeVirtualwares($cutoff),
                'cloud_tenants' => $this->purgeCloudTenants($cutoff),
            ],
        ];
    }

    private function releaseAllocations(): int
    {
        $released = 0;

        $softwareIds = Software::onlyTrashed()->pluck('id');
        if ($softwareIds->isNotEmpty()) {
            $released += SoftwareAssignment::query()->whereIn('software_id', $softwareIds)->delete();
            $released += UserwareAccount::query()
                ->whereIn('software_id', $softwareIds)
                ->update(['software_id' => null]);
        }

        $userwareIds = Userware::onlyTrashed()->pluck('id');
        if ($userwareIds->isNotEmpty()) {
            $released += Hardware::withTrashed()
                ->whereIn('assigned_userware_id', $userwareIds)
                ->update(['assigned_userware_id' => null]);
            $released += Virtualware::withTrashed()
                ->whereIn('assigned_userware_id', $userwareIds)
                ->update(['assigned_userware_id' => null]);
            $released += Software::withTrashed()
                ->whereIn('seat_manager_userware_id', $userwareIds)
                ->update(['seat_manager_userware_id' => null]);
            $released += SoftwareAssignment::query()->whereIn('userware_id', $userwareIds)->delete();
        }

        $hardwareIds = Hardware::onlyTrashed()->pluck('id');
        if ($hardwareIds->isNotEmpty()) {
            $released += Virtualware::withTrashed()
                ->whereIn('host_hardware_id', $hardwareIds)
                ->update(['host_hardware_id' => null]);
        }

        $tenantIds = CloudTenant::onlyTrashed()->pluck('id');
        if ($tenantIds->isNotEmpty()) {
            $released += Virtualware::withTrashed()
                ->whereIn('cloud_tenant_id', $tenantIds)
                ->update(['cloud_tenant_id' => null]);
        }

        return $released;
    }

    private function purgeSoftwares(CarbonInterface $cutoff): int
    {
        $count = 0;

        $softwares = Software::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->orderByRaw('case when parent_software_id is null then 1 else 0 end')
            ->orderBy('id')
            ->get();

        foreach ($softwares as $software) {
            $this->deleteMorphDocuments($software);
            $this->deleteMorphCostSnapshots($software);
            $software->forceDelete();
            $count++;
        }

        return $count;
    }

    private function purgeUserwares(CarbonInterface $cutoff): int
    {
        $count = 0;

        $userwares = Userware::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->orderBy('id')
            ->get();

        foreach ($userwares as $userware) {
            UserwareAccount::query()->where('userware_id', $userware->id)->delete();
            SoftwareAssignment::query()->where('userware_id', $userware->id)->delete();
            $userware->forceDelete();
            $count++;
        }

        return $count;
    }

    private function purgeHardwares(CarbonInterface $cutoff): int
    {
        $count = 0;

        $hardwares = Hardware::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->orderBy('id')
            ->get();

        foreach ($hardwares as $hardware) {
            $this->deleteMorphDocuments($hardware);
            $hardware->forceDelete();
            $count++;
        }

        return $count;
    }

    private function purgeVirtualwares(CarbonInterface $cutoff): int
    {
        $count = 0;

        $virtualwares = Virtualware::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->orderBy('id')
            ->get();

        foreach ($virtualwares as $virtualware) {
            $virtualware->forceDelete();
            $count++;
        }

        return $count;
    }

    private function purgeCloudTenants(CarbonInterface $cutoff): int
    {
        $count = 0;

        $tenants = CloudTenant::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->orderBy('id')
            ->get();

        foreach ($tenants as $tenant) {
            $this->deleteMorphCostSnapshots($tenant);
            $tenant->forceDelete();
            $count++;
        }

        return $count;
    }

    private function deleteMorphDocuments(Model $model): void
    {
        if (! method_exists($model, 'documents')) {
            return;
        }

        /** @var MorphMany<AssetDocument, Model> $documents */
        $documents = $model->documents();

        $documents->each(function (AssetDocument $document): void {
            Storage::disk('local')->delete($document->path);
            $document->delete();
        });
    }

    private function deleteMorphCostSnapshots(Model $model): void
    {
        if (! method_exists($model, 'costSnapshots')) {
            return;
        }

        /** @var MorphMany<CostSnapshot, Model> $snapshots */
        $snapshots = $model->costSnapshots();
        $snapshots->delete();
    }
}
