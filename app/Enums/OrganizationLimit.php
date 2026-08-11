<?php

namespace App\Enums;

enum OrganizationLimit: string
{
    case Members = 'member_limit';
    case Userware = 'userware_limit';
    case Hardware = 'hardware_limit';
    case Virtualware = 'virtualware_limit';
    case Software = 'software_limit';
    case CloudTenants = 'cloud_tenant_limit';
    case AssetDocuments = 'asset_document_limit';
    case UserwareAccounts = 'userware_account_limit';
    case ApiKeys = 'api_key_limit';

    public function label(): string
    {
        return match ($this) {
            self::Members => __('Members'),
            self::Userware => __('Userware'),
            self::Hardware => __('Hardware'),
            self::Virtualware => __('Virtualware'),
            self::Software => __('Software'),
            self::CloudTenants => __('Cloud tenants'),
            self::AssetDocuments => __('Asset documents'),
            self::UserwareAccounts => __('Userware accounts'),
            self::ApiKeys => __('API keys'),
        };
    }
}
