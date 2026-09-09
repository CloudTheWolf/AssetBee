<?php

namespace App\Enums;

enum AtlassianAddonSeatSource: string
{
    case Jira = 'jira';
    case Confluence = 'confluence';
    case Compass = 'compass';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Jira => __('Same as Jira seats'),
            self::Confluence => __('Same as Confluence seats'),
            self::Compass => __('Same as Compass seats'),
            self::Manual => __('Manual seat count'),
        };
    }

    public function usesHostProduct(): bool
    {
        return $this !== self::Manual;
    }

    public function hostProduct(): ?AtlassianCostProduct
    {
        return match ($this) {
            self::Jira => AtlassianCostProduct::Jira,
            self::Confluence => AtlassianCostProduct::Confluence,
            self::Compass => AtlassianCostProduct::Compass,
            self::Manual => null,
        };
    }
}
