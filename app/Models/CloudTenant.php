<?php

namespace App\Models;

use App\Enums\CloudTenantProvider;
use App\Enums\CloudTenantStatus;
use Database\Factories\CloudTenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
])]
class CloudTenant extends Model
{
    /** @use HasFactory<CloudTenantFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'cloud_tenants';

    /** @var list<string> */
    protected $hidden = [
        'credentials',
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
        ];
    }

    public function hasCredentials(): bool
    {
        return filled($this->credentials);
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
            default => [],
        };
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
}
