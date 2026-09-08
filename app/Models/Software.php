<?php

namespace App\Models;

use App\Enums\SoftwareBillingInterval;
use App\Enums\SoftwareCostSyncProvider;
use App\Enums\SoftwareLicenseType;
use App\Enums\SoftwareSeatManagerType;
use App\Enums\SoftwareStatus;
use Database\Factories\SoftwareFactory;
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
 * @property string|null $vendor
 * @property SoftwareLicenseType $license_type
 * @property int|null $total_seats
 * @property SoftwareSeatManagerType|null $seat_manager_type
 * @property int|null $seat_manager_userware_id
 * @property string|null $seat_manager_department
 * @property SoftwareStatus $status
 * @property Carbon|null $expires_at
 * @property bool $is_recurring
 * @property SoftwareBillingInterval|null $billing_interval
 * @property string|null $billing_amount
 * @property string $currency
 * @property Carbon|null $next_billing_at
 * @property string|null $notes
 * @property SoftwareCostSyncProvider $cost_sync_provider
 * @property array<string, mixed>|null $cost_sync_credentials
 * @property array<string, mixed>|null $cost_sync_request
 * @property Carbon|null $cost_synced_at
 * @property string|null $cost_sync_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'organization_id',
    'name',
    'vendor',
    'license_type',
    'total_seats',
    'seat_manager_type',
    'seat_manager_userware_id',
    'seat_manager_department',
    'status',
    'expires_at',
    'is_recurring',
    'billing_interval',
    'billing_amount',
    'currency',
    'next_billing_at',
    'notes',
    'cost_sync_provider',
    'cost_sync_credentials',
    'cost_sync_request',
    'cost_synced_at',
    'cost_sync_error',
])]
class Software extends Model
{
    /** @use HasFactory<SoftwareFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'softwares';

    /** @var list<string> */
    protected $hidden = [
        'cost_sync_credentials',
        'cost_sync_request',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'license_type' => SoftwareLicenseType::class,
            'seat_manager_type' => SoftwareSeatManagerType::class,
            'status' => SoftwareStatus::class,
            'expires_at' => 'date',
            'is_recurring' => 'boolean',
            'billing_interval' => SoftwareBillingInterval::class,
            'billing_amount' => 'decimal:2',
            'next_billing_at' => 'date',
            'total_seats' => 'integer',
            'cost_sync_provider' => SoftwareCostSyncProvider::class,
            'cost_sync_credentials' => 'encrypted:array',
            'cost_sync_request' => 'encrypted:array',
            'cost_synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Userware, $this>
     */
    public function seatManagerUserware(): BelongsTo
    {
        return $this->belongsTo(Userware::class, 'seat_manager_userware_id');
    }

    /**
     * @return HasMany<SoftwareAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(SoftwareAssignment::class);
    }

    /**
     * @return HasMany<SoftwareKey, $this>
     */
    public function keys(): HasMany
    {
        return $this->hasMany(SoftwareKey::class);
    }

    /**
     * @return MorphMany<AssetDocument, $this>
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(AssetDocument::class, 'documentable');
    }

    /**
     * @return MorphMany<CostSnapshot, $this>
     */
    public function costSnapshots(): MorphMany
    {
        return $this->morphMany(CostSnapshot::class, 'costable')->latest('period_start');
    }

    public function hasCostSyncCredentials(): bool
    {
        return filled($this->cost_sync_credentials);
    }

    public function hasCostSyncConfigured(): bool
    {
        return ($this->cost_sync_provider ?? SoftwareCostSyncProvider::None)->isConfigured();
    }

    /**
     * Non-secret credential fields safe to display in forms.
     *
     * @return array<string, string>
     */
    public function costSyncCredentialFormDefaults(): array
    {
        $credentials = $this->cost_sync_credentials ?? [];

        return match ($this->cost_sync_provider) {
            SoftwareCostSyncProvider::Atlassian => [
                'organization_id' => (string) ($credentials['organization_id'] ?? ''),
                'email' => (string) ($credentials['email'] ?? ''),
                'api_token' => '',
            ],
            SoftwareCostSyncProvider::GoogleWorkspace => [
                'customer_id' => (string) ($credentials['customer_id'] ?? ''),
                'service_account_email' => (string) ($credentials['service_account_email'] ?? ''),
                'service_account_json' => '',
                'admin_email' => (string) ($credentials['admin_email'] ?? ''),
            ],
            SoftwareCostSyncProvider::Cursor => [
                'team_id' => (string) ($credentials['team_id'] ?? ''),
                'api_key' => '',
            ],
            SoftwareCostSyncProvider::CustomHttp => [
                'bearer_token' => '',
                'username' => (string) ($credentials['username'] ?? ''),
                'password' => '',
                'header_name' => (string) ($credentials['header_name'] ?? ''),
                'header_value' => '',
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
        ];
    }

    public function seatManagerLabel(): ?string
    {
        return match ($this->seat_manager_type) {
            SoftwareSeatManagerType::Userware => $this->seatManagerUserware?->name,
            SoftwareSeatManagerType::Department => $this->seat_manager_department,
            default => null,
        };
    }

    public function seatsUsed(): int
    {
        return $this->assignments()->count();
    }

    public function seatsAvailable(): ?int
    {
        if ($this->license_type !== SoftwareLicenseType::Seat || $this->total_seats === null) {
            return null;
        }

        return max(0, $this->total_seats - $this->seatsUsed());
    }

    public function hasAvailableSeats(): bool
    {
        if ($this->license_type !== SoftwareLicenseType::Seat || $this->total_seats === null) {
            return true;
        }

        return $this->seatsUsed() < $this->total_seats;
    }

    public function keysUsed(): int
    {
        return $this->keys()->whereHas('assignment')->count();
    }

    public function keysAvailable(): ?int
    {
        if ($this->license_type !== SoftwareLicenseType::Key) {
            return null;
        }

        return max(0, $this->keys()->count() - $this->keysUsed());
    }

    public function hasAvailableKeys(): bool
    {
        if ($this->license_type !== SoftwareLicenseType::Key) {
            return true;
        }

        return $this->keys()->whereDoesntHave('assignment')->exists();
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
        if (! $this->is_recurring || $this->billing_amount === null || $this->billing_interval === null) {
            return null;
        }

        return round((float) $this->billing_amount / $this->billing_interval->monthsPerPeriod(), 2);
    }

    public function annualCost(): ?float
    {
        $monthly = $this->monthlyCost();

        return $monthly === null ? null : round($monthly * 12, 2);
    }
}
