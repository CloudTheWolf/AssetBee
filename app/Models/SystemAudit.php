<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $actor_id
 * @property string|null $actor_name
 * @property int|null $organization_id
 * @property string $action
 * @property string $target_type
 * @property int|null $target_id
 * @property string|null $summary
 * @property string|null $ip_address
 * @property Carbon $occurred_at
 */
#[Fillable([
    'actor_id',
    'actor_name',
    'organization_id',
    'action',
    'target_type',
    'target_id',
    'summary',
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

    /**
     * @param  Builder<SystemAudit>  $query
     * @param  list<int>  $memberIds
     * @return Builder<SystemAudit>
     */
    public function scopeVisibleToOrganization(Builder $query, Organization $organization, array $memberIds): Builder
    {
        return $query->where(function (Builder $query) use ($organization, $memberIds): void {
            $query->where('organization_id', $organization->id)
                ->orWhere(function (Builder $query) use ($memberIds): void {
                    $query->whereNull('organization_id')
                        ->whereIn('actor_id', $memberIds)
                        ->where('action', 'like', 'auth.%');
                });
        });
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'auth.login' => __('Signed in'),
            'auth.logout' => __('Signed out'),
            'auth.failed' => __('Sign-in failed'),
            'auth.password_reset' => __('Password reset'),
            'auth.password_changed' => __('Password changed'),
            'auth.email_verified' => __('Email verified'),
            'auth.two_factor_enabled' => __('Two-factor authentication enabled'),
            'auth.two_factor_disabled' => __('Two-factor authentication disabled'),
            'customer_context.entered' => __('Entered customer context'),
            'customer_context.exited' => __('Exited customer context'),
            'organization_member.removed' => __('Member removed'),
            'organization_member.role_updated' => __('Member role updated'),
            default => Str::headline(str_replace(['.', '_'], ' ', $this->action)),
        };
    }

    public function actorLabel(): string
    {
        return $this->actor_name
            ?? $this->actor->name
            ?? ($this->actor_id === null ? __('API') : __('Deleted user'));
    }

    public function targetLabel(): string
    {
        $type = Str::headline(class_basename($this->target_type));

        if (filled($this->summary)) {
            return $type.': '.$this->summary;
        }

        if ($this->target_id !== null) {
            return $type.' #'.$this->target_id;
        }

        return $type;
    }
}
