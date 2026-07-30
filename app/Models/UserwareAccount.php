<?php

namespace App\Models;

use Database\Factories\UserwareAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $userware_id
 * @property int|null $software_id
 * @property string|null $site_name
 * @property string|null $site_url
 * @property string|null $username
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id',
    'userware_id',
    'software_id',
    'site_name',
    'site_url',
    'username',
    'notes',
])]
class UserwareAccount extends Model
{
    /** @use HasFactory<UserwareAccountFactory> */
    use HasFactory;

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
    public function userware(): BelongsTo
    {
        return $this->belongsTo(Userware::class);
    }

    /**
     * @return BelongsTo<Software, $this>
     */
    public function software(): BelongsTo
    {
        return $this->belongsTo(Software::class);
    }

    public function isLinkedToSoftware(): bool
    {
        return $this->software_id !== null;
    }

    public function displayName(): string
    {
        if ($this->software !== null) {
            return $this->software->name;
        }

        return (string) ($this->site_name ?? __('Untitled account'));
    }

    public function displayUrl(): ?string
    {
        return $this->site_url;
    }
}
