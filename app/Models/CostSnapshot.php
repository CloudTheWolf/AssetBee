<?php

namespace App\Models;

use App\Enums\CostSyncSource;
use Database\Factories\CostSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $costable_type
 * @property int $costable_id
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property string $amount
 * @property string $currency
 * @property int|null $seat_count
 * @property CostSyncSource $provider
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id',
    'costable_type',
    'costable_id',
    'period_start',
    'period_end',
    'amount',
    'currency',
    'seat_count',
    'provider',
    'meta',
    'synced_at',
])]
class CostSnapshot extends Model
{
    /** @use HasFactory<CostSnapshotFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'amount' => 'decimal:2',
            'seat_count' => 'integer',
            'provider' => CostSyncSource::class,
            'meta' => 'array',
            'synced_at' => 'datetime',
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
     * @return MorphTo<Model, $this>
     */
    public function costable(): MorphTo
    {
        return $this->morphTo();
    }

    public function formattedAmount(): string
    {
        return strtoupper($this->currency).' '.number_format((float) $this->amount, 2);
    }
}
