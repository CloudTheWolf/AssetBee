<?php

namespace App\Models;

use App\Enums\SoftwareLicenseType;
use App\Enums\SoftwareStatus;
use Database\Factories\SoftwareFactory;
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
 * @property string|null $vendor
 * @property SoftwareLicenseType $license_type
 * @property int|null $total_seats
 * @property SoftwareStatus $status
 * @property Carbon|null $expires_at
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
}
