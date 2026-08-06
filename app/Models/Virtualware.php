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
 * @property int|null $cloud_tenant_id
 * @property int|null $assigned_userware_id
 * @property string|null $notes
 * @property string|null $region
 * @property string|null $instance_type
 * @property string|null $private_ip
 * @property string|null $public_ip
 * @property string|null $availability_zone
 * @property string|null $subnet_id
 * @property string|null $vpc_id
 * @property list<array{private_ip: string, public_ip: string|null, network_interface_id: string|null}>|null $secondary_ips
 * @property list<array{device_name?: string, volume_id?: string|null, size_gb?: int|null, volume_type?: string|null, encrypted?: bool|null, delete_on_termination?: bool|null}>|null $disks
 * @property bool|null $termination_protection
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
    'cloud_tenant_id',
    'assigned_userware_id',
    'notes',
    'region',
    'instance_type',
    'private_ip',
    'public_ip',
    'availability_zone',
    'subnet_id',
    'vpc_id',
    'secondary_ips',
    'disks',
    'termination_protection',
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
            'secondary_ips' => 'array',
            'disks' => 'array',
            'termination_protection' => 'boolean',
        ];
    }

    public function totalDiskSizeGb(): ?int
    {
        if ($this->disks === null || $this->disks === []) {
            return null;
        }

        $total = collect($this->disks)->sum(fn (array $disk): int => (int) ($disk['size_gb'] ?? 0));

        return $total > 0 ? $total : null;
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
     * @return BelongsTo<CloudTenant, $this>
     */
    public function cloudTenant(): BelongsTo
    {
        return $this->belongsTo(CloudTenant::class);
    }

    /**
     * @return BelongsTo<Userware, $this>
     */
    public function assignedUserware(): BelongsTo
    {
        return $this->belongsTo(Userware::class, 'assigned_userware_id');
    }
}
