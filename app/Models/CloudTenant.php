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
])]
class CloudTenant extends Model
{
    /** @use HasFactory<CloudTenantFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'cloud_tenants';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => CloudTenantProvider::class,
            'status' => CloudTenantStatus::class,
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
     * @return HasMany<Virtualware, $this>
     */
    public function virtualwares(): HasMany
    {
        return $this->hasMany(Virtualware::class);
    }
}
