<?php

namespace App\Enums;

enum VirtualwareProvider: string
{
    case Aws = 'aws';
    case Azure = 'azure';
    case Gcp = 'gcp';
    case Vmware = 'vmware';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Aws => __('AWS'),
            self::Azure => __('Azure'),
            self::Gcp => __('GCP'),
            self::Vmware => __('VMware'),
            self::Other => __('Other'),
        };
    }
}
