<?php

namespace App\Models;

use App\Enums\HardwareCategory;
use App\Enums\HardwareStatus;
use Database\Factories\HardwareFactory;
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
 * @property string|null $asset_tag
 * @property string|null $serial_number
 * @property string|null $manufacturer
 * @property string|null $model
 * @property HardwareCategory $category
 * @property HardwareStatus $status
 * @property int|null $assigned_userware_id
 * @property Carbon|null $purchased_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'organization_id',
    'name',
    'asset_tag',
    'serial_number',
    'manufacturer',
    'model',
    'category',
    'status',
    'assigned_userware_id',
    'purchased_at',
    'notes',
])]
class Hardware extends Model
{
    /** @use HasFactory<HardwareFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'hardwares';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => HardwareCategory::class,
            'status' => HardwareStatus::class,
            'purchased_at' => 'date',
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
    public function assignedUserware(): BelongsTo
    {
        return $this->belongsTo(Userware::class, 'assigned_userware_id');
    }

    /**
     * @return HasMany<Virtualware, $this>
     */
    public function virtualwares(): HasMany
    {
        return $this->hasMany(Virtualware::class, 'host_hardware_id');
    }
}
