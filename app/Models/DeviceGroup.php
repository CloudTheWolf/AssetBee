<?php

namespace App\Models;

use Database\Factories\DeviceGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read Collection<int, Hardware> $hardwares
 */
#[Fillable(['organization_id', 'name', 'description'])]
class DeviceGroup extends Model
{
    /** @use HasFactory<DeviceGroupFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsToMany<Hardware, $this>
     */
    public function hardwares(): BelongsToMany
    {
        return $this->belongsToMany(Hardware::class, 'device_group_hardware')
            ->withTimestamps();
    }
}
