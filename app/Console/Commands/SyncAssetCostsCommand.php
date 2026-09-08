<?php

namespace App\Console\Commands;

use App\Actions\Assets\SyncOrganizationAssetCosts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cost:sync')]
#[Description('Sync software and cloud tenant costs from configured providers')]
class SyncAssetCostsCommand extends Command
{
    public function handle(SyncOrganizationAssetCosts $syncOrganizationAssetCosts): int
    {
        $this->components->info('Syncing asset costs...');
        $result = $syncOrganizationAssetCosts->handle();

        $this->components->twoColumnDetail('Organizations', (string) $result['organizations']);
        $this->components->twoColumnDetail('Assets', (string) $result['assets']);
        $this->components->twoColumnDetail('Synced', (string) $result['synced']);
        $this->components->twoColumnDetail('Failed', (string) $result['failed']);

        foreach ($result['errors'] as $error) {
            $this->components->warn($error);
        }

        if ($result['failed'] > 0) {
            $this->components->warn('Cost sync finished with some failures. See warnings above.');
        } else {
            $this->components->info('Cost sync completed.');
        }

        return self::SUCCESS;
    }
}
