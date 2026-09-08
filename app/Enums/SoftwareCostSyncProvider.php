<?php

namespace App\Enums;

enum SoftwareCostSyncProvider: string
{
    case None = 'none';
    case Atlassian = 'atlassian';
    case GoogleWorkspace = 'google_workspace';
    case Cursor = 'cursor';
    case CustomHttp = 'custom_http';

    public function label(): string
    {
        return match ($this) {
            self::None => __('None'),
            self::Atlassian => __('Atlassian'),
            self::GoogleWorkspace => __('Google Workspace'),
            self::Cursor => __('Cursor'),
            self::CustomHttp => __('Custom HTTP'),
        };
    }

    public function isConfigured(): bool
    {
        return $this !== self::None;
    }
}
