<?php

namespace App\Enums;

enum CostSyncSource: string
{
    case Aws = 'aws';
    case Azure = 'azure';
    case Atlassian = 'atlassian';
    case GoogleWorkspace = 'google_workspace';
    case Cursor = 'cursor';
    case CustomHttp = 'custom_http';

    public function label(): string
    {
        return match ($this) {
            self::Aws => __('AWS'),
            self::Azure => __('Azure'),
            self::Atlassian => __('Atlassian'),
            self::GoogleWorkspace => __('Google Workspace'),
            self::Cursor => __('Cursor'),
            self::CustomHttp => __('Custom HTTP'),
        };
    }
}
