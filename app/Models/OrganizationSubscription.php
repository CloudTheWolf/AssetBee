<?php

namespace App\Models;

use App\Enums\SubscriptionBillingInterval;
use App\Enums\SubscriptionStatus;
use Database\Factories\OrganizationSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $plan_name
 * @property SubscriptionStatus $status
 * @property string $price
 * @property string $currency
 * @property SubscriptionBillingInterval $billing_interval
 * @property string|null $stripe_price_id
 * @property Carbon|null $renews_at
 * @property int|null $member_limit
 * @property int|null $userware_limit
 * @property int|null $hardware_limit
 * @property int|null $virtualware_limit
 * @property int|null $software_limit
 * @property int|null $cloud_tenant_limit
 * @property int|null $asset_document_limit
 * @property int|null $userware_account_limit
 * @property int|null $api_key_limit
 */
#[Fillable([
    'organization_id', 'plan_name', 'status', 'price', 'currency',
    'billing_interval', 'stripe_price_id', 'renews_at', 'member_limit', 'userware_limit',
    'hardware_limit', 'virtualware_limit', 'software_limit',
    'cloud_tenant_limit', 'asset_document_limit', 'userware_account_limit',
    'api_key_limit',
])]
class OrganizationSubscription extends Model
{
    /** @use HasFactory<OrganizationSubscriptionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'price' => 'decimal:2',
            'billing_interval' => SubscriptionBillingInterval::class,
            'renews_at' => 'date',
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

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
