<?php

namespace App\Models;

use App\Enums\RemotePackageSource;
use Database\Factories\RemotePackageFactory;
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
 * @property string $name
 * @property RemotePackageSource $source
 * @property string|null $msi_url
 * @property string|null $msi_args
 * @property string|null $winget_id
 * @property string|null $choco_id
 * @property string|null $silent_args
 * @property string|null $sha256
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read Collection<int, RemotePush> $remotePushes
 */
#[Fillable([
    'organization_id',
    'name',
    'source',
    'msi_url',
    'msi_args',
    'winget_id',
    'choco_id',
    'silent_args',
    'sha256',
])]
class RemotePackage extends Model
{
    /** @use HasFactory<RemotePackageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => RemotePackageSource::class,
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
     * @return HasMany<RemotePush, $this>
     */
    public function remotePushes(): HasMany
    {
        return $this->hasMany(RemotePush::class);
    }

    /**
     * @return BelongsToMany<RemotePush, $this>
     */
    public function pushes(): BelongsToMany
    {
        return $this->belongsToMany(RemotePush::class, 'remote_push_package')
            ->withPivot('position')
            ->withTimestamps();
    }

    /**
     * @return array<string, mixed>
     */
    public function toDronePayload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'source' => $this->source->value,
            'msiUrl' => $this->msi_url,
            'msiArgs' => $this->msi_args,
            'wingetId' => $this->winget_id,
            'chocoId' => $this->choco_id,
            'silentArgs' => $this->silent_args,
            'sha256' => $this->sha256,
        ];
    }
}
