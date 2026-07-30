<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, OrganizationGoogleDomain> $googleDomains
 */
#[Fillable(['name', 'slug'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(OrganizationUser::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<OrganizationGoogleDomain, $this>
     */
    public function googleDomains(): HasMany
    {
        return $this->hasMany(OrganizationGoogleDomain::class);
    }

    /**
     * @return HasMany<OrganizationInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
    }

    /**
     * @return HasMany<Userware, $this>
     */
    public function userwares(): HasMany
    {
        return $this->hasMany(Userware::class);
    }

    /**
     * @return HasMany<Hardware, $this>
     */
    public function hardwares(): HasMany
    {
        return $this->hasMany(Hardware::class);
    }

    /**
     * @return HasMany<Virtualware, $this>
     */
    public function virtualwares(): HasMany
    {
        return $this->hasMany(Virtualware::class);
    }

    /**
     * @return HasMany<Software, $this>
     */
    public function softwares(): HasMany
    {
        return $this->hasMany(Software::class);
    }

    /**
     * @return HasMany<CloudTenant, $this>
     */
    public function cloudTenants(): HasMany
    {
        return $this->hasMany(CloudTenant::class);
    }

    /**
     * @return HasMany<AssetDocument, $this>
     */
    public function assetDocuments(): HasMany
    {
        return $this->hasMany(AssetDocument::class);
    }

    public static function findByGoogleDomain(string $domain): ?self
    {
        return static::query()
            ->whereHas('googleDomains', fn ($query) => $query->where('domain', strtolower($domain)))
            ->first();
    }
}
