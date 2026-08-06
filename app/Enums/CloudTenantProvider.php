<?php

namespace App\Enums;

enum CloudTenantProvider: string
{
    case Microsoft365 = 'microsoft365';
    case Aws = 'aws';
    case Azure = 'azure';
    case Gcp = 'gcp';
    case GoogleWorkspace = 'google_workspace';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Microsoft365 => __('Microsoft 365'),
            self::Aws => __('AWS'),
            self::Azure => __('Azure'),
            self::Gcp => __('GCP'),
            self::GoogleWorkspace => __('Google Workspace'),
            self::Other => __('Other'),
        };
    }

    public function supportsCredentials(): bool
    {
        return match ($this) {
            self::Aws, self::Azure, self::Gcp => true,
            default => false,
        };
    }

    public function supportsVmImport(): bool
    {
        return match ($this) {
            self::Aws => true,
            default => false,
        };
    }
}
