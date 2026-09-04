<?php

namespace App\Console\Commands;

use App\Actions\Assets\SyncAllProxmoxVirtualware;
use App\Actions\Assets\SyncImportedAwsEc2Instances;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('virtualware:sync')]
#[Description('Refresh imported AWS EC2 virtualware and sync all Proxmox guests')]
class SyncVirtualwareCommand extends Command
{
    public function handle(
        SyncImportedAwsEc2Instances $syncImportedAwsEc2Instances,
        SyncAllProxmoxVirtualware $syncAllProxmoxVirtualware,
    ): int {
        $this->components->info('Syncing imported AWS EC2 instances...');
        $aws = $syncImportedAwsEc2Instances->handle();
        $this->components->twoColumnDetail('AWS tenants', (string) $aws['tenants']);
        $this->components->twoColumnDetail('AWS updated', (string) $aws['updated']);
        $this->components->twoColumnDetail('AWS created', (string) $aws['created']);
        $this->components->twoColumnDetail('AWS failed', (string) $aws['failed']);

        foreach ($aws['errors'] as $error) {
            $this->components->warn($error);
        }

        $this->newLine();
        $this->components->info('Syncing Proxmox virtualware...');
        $proxmox = $syncAllProxmoxVirtualware->handle();
        $this->components->twoColumnDetail('Proxmox hosts', (string) $proxmox['hosts']);
        $this->components->twoColumnDetail('Proxmox created', (string) $proxmox['created']);
        $this->components->twoColumnDetail('Proxmox updated', (string) $proxmox['updated']);
        $this->components->twoColumnDetail('Proxmox failed', (string) $proxmox['failed']);

        foreach ($proxmox['errors'] as $error) {
            $this->components->warn($error);
        }

        if ($aws['failed'] > 0 || $proxmox['failed'] > 0) {
            $this->components->warn('Virtualware sync finished with some host or tenant failures. See warnings above.');
        } else {
            $this->components->info('Virtualware sync completed.');
        }

        // Partial provider failures are logged as warnings; exiting non-zero
        // makes the scheduler report an ERROR even when sync otherwise ran.
        return self::SUCCESS;
    }
}
