<?php

namespace App\Models;

use App\Enums\SoftwareBillingInterval;
use App\Enums\SoftwareLicenseType;
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
 * @property SoftwareStatus $status
 * @property Carbon|null $expires_at
 * @property bool $is_recurring
 * @property SoftwareBillingInterval|null $billing_interval
 * @property string|null $billing_amount
 * @property string $currency
 * @property Carbon|null $next_billing_at
 * @property string|null $notes
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
    'status',
    'expires_at',
    'is_recurring',
    'billing_interval',
    'billing_amount',
    'currency',
    'next_billing_at',
    'notes',
])]
class Software extends Model
{
    /** @use HasFactory<SoftwareFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'softwares';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'license_type' => SoftwareLicenseType::class,
            'status' => SoftwareStatus::class,
            'expires_at' => 'date',
            'is_recurring' => 'boolean',
            'billing_interval' => SoftwareBillingInterval::class,
            'billing_amount' => 'decimal:2',
            'next_billing_at' => 'date',
            'total_seats' => 'integer',
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
     * @return HasMany<SoftwareAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(SoftwareAssignment::class);
    }

    /**
     * @return MorphMany<AssetDocument, $this>
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(AssetDocument::class, 'documentable');
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

    public function formattedBillingAmount(): ?string
    {
        if ($this->billing_amount === null) {
            return null;
        }

        return strtoupper($this->currency).' '.number_format((float) $this->billing_amount, 2);
    }
}
