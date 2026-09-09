<?php

namespace App\Console\Commands;

use App\Actions\Assets\SyncOrganizationAssetCosts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cost:sync {--all : Sync every organization with cost sync configured, even when Automatic cost sync is disabled}')]
#[Description('Sync software and cloud tenant costs from configured providers')]
class SyncAssetCostsCommand extends Command
{
    public function handle(SyncOrganizationAssetCosts $syncOrganizationAssetCosts): int
    {
        $allOrganizations = (bool) $this->option('all');

        $this->components->info($allOrganizations
            ? 'Syncing asset costs for all organizations with configured providers...'
            : 'Syncing asset costs for organizations with Automatic cost sync enabled...');

        $result = $syncOrganizationAssetCosts->handle($allOrganizations);

        $this->components->twoColumnDetail('Organizations', (string) $result['organizations']);
        $this->components->twoColumnDetail('Assets', (string) $result['assets']);
        $this->components->twoColumnDetail('Synced', (string) $result['synced']);
        $this->components->twoColumnDetail('Failed', (string) $result['failed']);

        foreach ($result['errors'] as $error) {
            $this->components->warn($error);
        }

        if ($result['organizations'] === 0 && $result['assets'] === 0) {
            if ($result['skipped_organizations'] > 0 && ! $allOrganizations) {
                $this->components->warn(__(':count organization(s) have cost sync configured on assets, but Automatic cost sync is disabled.', [
                    'count' => $result['skipped_organizations'],
                ]));
                $this->components->warn(__('Enable Automatic cost sync in Organization settings, or run: php artisan cost:sync --all'));
            } else {
                $this->components->warn(__('No organizations or assets were eligible for cost sync.'));
                $this->components->warn(__('Configure a cost sync provider on software/cloud tenants, then enable Automatic cost sync (or use --all).'));
            }
        } elseif ($result['failed'] > 0) {
            $this->components->warn('Cost sync finished with some failures. See warnings above.');
        } else {
            $this->components->info('Cost sync completed.');
        }

        return self::SUCCESS;
    }
}
