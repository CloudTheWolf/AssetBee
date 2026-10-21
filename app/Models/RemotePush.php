<?php

namespace App\Models;

use App\Enums\RemotePushStatus;
use App\Enums\RemotePushTargetStatus;
use App\Enums\RemotePushType;
use Database\Factories\RemotePushFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $created_by
 * @property RemotePushType $type
 * @property int|null $remote_package_id
 * @property RemotePushStatus $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read User|null $creator
 * @property-read RemotePackage|null $package
 * @property-read Collection<int, RemotePackage> $packages
 * @property-read Collection<int, RemotePushTarget> $targets
 */
#[Fillable([
    'organization_id',
    'created_by',
    'type',
    'remote_package_id',
    'status',
    'notes',
])]
class RemotePush extends Model
{
    /** @use HasFactory<RemotePushFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => RemotePushType::class,
            'status' => RemotePushStatus::class,
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Primary/legacy package column (first package). Prefer {@see packages()}.
     *
     * @return BelongsTo<RemotePackage, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(RemotePackage::class, 'remote_package_id');
    }

    /**
     * @return BelongsToMany<RemotePackage, $this>
     */
    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(RemotePackage::class, 'remote_push_package')
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }

    /**
     * @return HasMany<RemotePushTarget, $this>
     */
    public function targets(): HasMany
    {
        return $this->hasMany(RemotePushTarget::class);
    }

    public function packageSummary(): string
    {
        $names = $this->relationLoaded('packages')
            ? $this->packages->pluck('name')
            : $this->packages()->pluck('remote_packages.name');

        if ($names->isEmpty()) {
            return $this->package !== null ? $this->package->name : '';
        }

        if ($names->count() === 1) {
            return (string) $names->first();
        }

        return $names->take(2)->implode(', ').($names->count() > 2 ? ' +'.($names->count() - 2) : '');
    }

    public function refreshCompletionStatus(): void
    {
        if ($this->status === RemotePushStatus::Cancelled) {
            return;
        }

        $incomplete = $this->targets()
            ->whereIn('status', array_map(
                static fn (RemotePushTargetStatus $status): string => $status->value,
                RemotePushTargetStatus::incompleteCases(),
            ))
            ->exists();

        $this->update([
            'status' => $incomplete ? RemotePushStatus::Open : RemotePushStatus::Completed,
        ]);
    }
}
