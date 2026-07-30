<?php

namespace App\Models;

use App\Enums\VirtualwareCategory;
use App\Enums\VirtualwareProvider;
use App\Enums\VirtualwareStatus;
use Database\Factories\VirtualwareFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property VirtualwareProvider $provider
 * @property string|null $external_id
 * @property VirtualwareCategory $category
 * @property VirtualwareStatus $status
 * @property int|null $host_hardware_id
 * @property int|null $assigned_userware_id
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'organization_id',
    'name',
    'provider',
    'external_id',
    'category',
    'status',
    'host_hardware_id',
    'assigned_userware_id',
    'notes',
])]
class Virtualware extends Model
{
    /** @use HasFactory<VirtualwareFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'virtualwares';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => VirtualwareProvider::class,
            'category' => VirtualwareCategory::class,
            'status' => VirtualwareStatus::class,
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
     * @return BelongsTo<Hardware, $this>
     */
    public function hostHardware(): BelongsTo
    {
        return $this->belongsTo(Hardware::class, 'host_hardware_id');
    }

    /**
     * @return BelongsTo<Userware, $this>
     */
    public function assignedUserware(): BelongsTo
    {
        return $this->belongsTo(Userware::class, 'assigned_userware_id');
    }
}
