<?php

namespace App\Actions\Assets;

use App\Models\Hardware;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncAllProxmoxVirtualware
{
    public function __construct(
        protected DiscoverProxmoxGuests $discoverProxmoxGuests,
        protected ImportProxmoxGuests $importProxmoxGuests,
    ) {}

    /**
     * Discover and import/update all guests from every Proxmox-connected VM host.
     *
     * @return array{hosts: int, created: int, updated: int, failed: int, errors: list<string>}
     */
    public function handle(): array
    {
        $hosts = Hardware::query()
            ->vmHosts()
            ->whereNotNull('proxmox_credentials')
            ->whereHas('organization', fn ($query) => $query->where('virtualware_sync_enabled', true))
            ->get()
            ->filter(fn (Hardware $hardware): bool => $hardware->hasProxmoxCredentials());

        $created = 0;
        $updated = 0;
        $failed = 0;
        /** @var list<string> $errors */
        $errors = [];

        foreach ($hosts as $host) {
            try {
                $discovered = $this->discoverProxmoxGuests->handle($host);

                /** @var list<string> $externalIds */
                $externalIds = array_map(
                    fn ($guest): string => $guest->externalId,
                    $discovered,
                );

                if ($externalIds === []) {
                    continue;
                }

                $result = $this->importProxmoxGuests->handle($host, $externalIds);
                $created += $result['created'];
                $updated += $result['updated'];
            } catch (Throwable $exception) {
                $failed++;
                $message = __('Proxmox host :name (#:id): :error', [
                    'name' => $host->name,
                    'id' => $host->id,
                    'error' => $exception->getMessage(),
                ]);
                $errors[] = $message;
                Log::warning($message, ['exception' => $exception]);
            }
        }

        return [
            'hosts' => $hosts->count(),
            'created' => $created,
            'updated' => $updated,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }
}
