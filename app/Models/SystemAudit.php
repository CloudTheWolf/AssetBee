<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $actor_id
 * @property int|null $organization_id
 * @property string $action
 * @property string $target_type
 * @property int|null $target_id
 * @property string|null $ip_address
 * @property Carbon $occurred_at
 */
#[Fillable([
    'actor_id',
    'organization_id',
    'action',
    'target_type',
    'target_id',
    'ip_address',
    'occurred_at',
])]
class SystemAudit extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
