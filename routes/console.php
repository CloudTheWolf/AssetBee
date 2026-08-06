<?php

use App\Models\Organization;
use App\Models\OrganizationApiKey;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('organization:api-key {organization} {--name=Inventory collector}', function () {
    $identifierValue = $this->argument('organization');

    if (! is_string($identifierValue) && ! is_int($identifierValue)) {
        $this->error('The organization must be an ID or slug.');

        return 1;
    }

    $identifier = (string) $identifierValue;
    $organization = Organization::query()
        ->where('id', $identifier)
        ->orWhere('slug', $identifier)
        ->first();

    if ($organization === null) {
        $this->error("Organization [{$identifier}] was not found.");

        return 1;
    }

    $name = $this->option('name');
    $name = is_string($name) && $name !== '' ? $name : 'Inventory collector';

    [, $plainTextKey] = OrganizationApiKey::issue($organization, $name);

    $this->warn('Copy this API key now. It will not be shown again:');
    $this->line($plainTextKey);

    return 0;
})->purpose('Issue an inventory API key for an organization');
