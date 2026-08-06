<?php

namespace App\Models;

use App\Enums\BitLockerStatus;
use App\Enums\HardwareCategory;
use App\Enums\HardwareOperatingSystem;
use App\Enums\HardwareStatus;
use Database\Factories\HardwareFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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
 * @property string|null $asset_tag
 * @property string|null $serial_number
 * @property string|null $manufacturer
 * @property string|null $model
 * @property HardwareOperatingSystem|null $operating_system
 * @property string|null $cpu
 * @property int|null $ram_gb
 * @property int|null $storage_gb
 * @property BitLockerStatus|null $bitlocker_status
 * @property string|null $bitlocker_recovery_key
 * @property bool $is_vm_host
 * @property Carbon|null $inventory_collected_at
 * @property array<string, mixed>|null $inventory_payload
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
    'operating_system',
    'cpu',
    'ram_gb',
    'storage_gb',
    'bitlocker_status',
    'bitlocker_recovery_key',
    'is_vm_host',
    'inventory_collected_at',
    'inventory_payload',
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
            'operating_system' => HardwareOperatingSystem::class,
            'bitlocker_status' => BitLockerStatus::class,
            'bitlocker_recovery_key' => 'encrypted',
            'is_vm_host' => 'boolean',
            'inventory_collected_at' => 'datetime',
            'inventory_payload' => 'encrypted:array',
            'ram_gb' => 'integer',
            'storage_gb' => 'integer',
            'purchased_at' => 'date',
        ];
    }

    /**
     * @param  Builder<Hardware>  $query
     * @return Builder<Hardware>
     */
    public function scopeVmHosts(Builder $query): Builder
    {
        return $query->where('is_vm_host', true)->where('category', HardwareCategory::Server);
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

    /**
     * @return MorphMany<AssetDocument, $this>
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(AssetDocument::class, 'documentable');
    }
}
