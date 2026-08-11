<?php

namespace App\Models;

use App\Enums\SubscriptionBillingInterval;
use Database\Factories\SubscriptionPackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'description', 'is_active', 'price', 'currency', 'billing_interval',
    'stripe_price_id', 'sort_order', 'member_limit', 'userware_limit',
    'hardware_limit', 'virtualware_limit', 'software_limit', 'cloud_tenant_limit',
    'asset_document_limit', 'userware_account_limit', 'api_key_limit',
])]
class SubscriptionPackage extends Model
{
    /** @use HasFactory<SubscriptionPackageFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'price' => 'decimal:2',
            'billing_interval' => SubscriptionBillingInterval::class,
            'sort_order' => 'integer',
            'member_limit' => 'integer',
            'userware_limit' => 'integer',
            'hardware_limit' => 'integer',
            'virtualware_limit' => 'integer',
            'software_limit' => 'integer',
            'cloud_tenant_limit' => 'integer',
            'asset_document_limit' => 'integer',
            'userware_account_limit' => 'integer',
            'api_key_limit' => 'integer',
        ];
    }

    /** @return HasMany<Organization, $this> */
    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class);
    }
}
