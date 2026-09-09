<?php

namespace App\Models;

use App\Enums\CloudTenantCostSyncProvider;
use App\Enums\CloudTenantProvider;
use App\Enums\CloudTenantStatus;
use App\Enums\SoftwareBillingInterval;
use Database\Factories\CloudTenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property CloudTenantProvider $provider
 * @property string|null $external_id
 * @property string|null $domain
 * @property CloudTenantStatus $status
 * @property string|null $notes
 * @property array<string, mixed>|null $credentials
 * @property Carbon|null $credentials_verified_at
 * @property CloudTenantCostSyncProvider $cost_sync_provider
 * @property array<string, mixed>|null $cost_sync_request
 * @property string|null $billing_amount
 * @property string $currency
 * @property SoftwareBillingInterval|null $billing_interval
 * @property Carbon|null $cost_synced_at
 * @property string|null $cost_sync_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'organization_id',
    'name',
    'provider',
    'external_id',
    'domain',
    'status',
    'notes',
    'credentials',
    'credentials_verified_at',
    'cost_sync_provider',
    'cost_sync_request',
    'billing_amount',
    'currency',
    'billing_interval',
    'cost_synced_at',
    'cost_sync_error',
])]
class CloudTenant extends Model
{
    /** @use HasFactory<CloudTenantFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'cloud_tenants';

    /** @var list<string> */
    protected $hidden = [
        'credentials',
        'cost_sync_request',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => CloudTenantProvider::class,
            'status' => CloudTenantStatus::class,
            'credentials' => 'encrypted:array',
            'credentials_verified_at' => 'datetime',
            'cost_sync_provider' => CloudTenantCostSyncProvider::class,
            'cost_sync_request' => 'encrypted:array',
            'billing_amount' => 'decimal:2',
            'billing_interval' => SoftwareBillingInterval::class,
            'cost_synced_at' => 'datetime',
        ];
    }

    public function hasCredentials(): bool
    {
        return filled($this->credentials);
    }

    public function hasCostSyncConfigured(): bool
    {
        return ($this->cost_sync_provider ?? CloudTenantCostSyncProvider::None)->isConfigured();
    }

    /**
     * Non-secret credential fields safe to display in forms.
     *
     * @return array<string, string>
     */
    public function credentialFormDefaults(): array
    {
        $credentials = $this->credentials ?? [];

        return match ($this->provider) {
            CloudTenantProvider::Aws => [
                'access_key_id' => (string) ($credentials['access_key_id'] ?? ''),
                'secret_access_key' => '',
                'region' => (string) ($credentials['region'] ?? 'us-east-1'),
                'session_token' => '',
            ],
            CloudTenantProvider::Azure => [
                'tenant_id' => (string) ($credentials['tenant_id'] ?? ''),
                'client_id' => (string) ($credentials['client_id'] ?? ''),
                'client_secret' => '',
                'subscription_id' => (string) ($credentials['subscription_id'] ?? ''),
            ],
            CloudTenantProvider::Gcp => [
                'project_id' => (string) ($credentials['project_id'] ?? ''),
                'service_account_json' => '',
            ],
            CloudTenantProvider::GoogleWorkspace => [
                'customer_id' => (string) ($credentials['customer_id'] ?? ''),
                'service_account_email' => (string) ($credentials['service_account_email'] ?? ''),
                'service_account_json' => '',
                'admin_email' => (string) ($credentials['admin_email'] ?? ''),
            ],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function costSyncRequestFormDefaults(): array
    {
        $request = $this->cost_sync_request ?? [];

        return [
            'method' => (string) ($request['method'] ?? 'GET'),
            'url' => (string) ($request['url'] ?? ''),
            'headers' => is_array($request['headers'] ?? null) ? $request['headers'] : [],
            'auth' => (string) ($request['auth'] ?? 'none'),
            'body' => (string) ($request['body'] ?? ''),
            'response_amount_path' => (string) ($request['response_amount_path'] ?? 'amount'),
            'response_currency_path' => (string) ($request['response_currency_path'] ?? 'currency'),
            'response_seats_path' => (string) ($request['response_seats_path'] ?? ''),
            'amount_period' => (string) ($request['amount_period'] ?? 'month'),
            'amount_source' => (string) ($request['amount_source'] ?? 'response'),
            'calculation_included_seats' => (string) ($request['calculation_included_seats'] ?? '0'),
            'calculation_price_per_seat' => isset($request['calculation_price_per_seat'])
                ? (string) $request['calculation_price_per_seat']
                : '',
            'calculation_currency' => (string) ($request['calculation_currency'] ?? $this->currency ?: 'GBP'),
        ];
    }

    public function formattedBillingAmount(): ?string
    {
        if ($this->billing_amount === null) {
            return null;
        }

        return strtoupper($this->currency).' '.number_format((float) $this->billing_amount, 2);
    }

    public function monthlyCost(): ?float
    {
        if ($this->billing_amount === null || $this->billing_interval === null) {
            return null;
        }

        return round((float) $this->billing_amount / $this->billing_interval->monthsPerPeriod(), 2);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return HasMany<Virtualware, $this>
     */
    public function virtualwares(): HasMany
    {
        return $this->hasMany(Virtualware::class);
    }

    /**
     * @return MorphMany<CostSnapshot, $this>
     */
    public function costSnapshots(): MorphMany
    {
        return $this->morphMany(CostSnapshot::class, 'costable')->latest('period_start');
    }
}
