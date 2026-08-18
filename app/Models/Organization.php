<?php

namespace App\Models;

use App\Enums\OrganizationRole;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Cashier\Billable;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int|null $subscription_package_id
 * @property string|null $stripe_id
 * @property string|null $pm_type
 * @property string|null $pm_last_four
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, OrganizationGoogleDomain> $googleDomains
 * @property-read OrganizationSubscription|null $plan
 * @property-read SubscriptionPackage|null $package
 */
#[Fillable(['name', 'slug'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use Billable, HasFactory;

    /**
     * @return BelongsToMany<User, $this, OrganizationUser>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(OrganizationUser::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<OrganizationApiKey, $this>
     */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(OrganizationApiKey::class);
    }

    /**
     * @return HasOne<OrganizationSubscription, $this>
     */
    public function plan(): HasOne
    {
        return $this->hasOne(OrganizationSubscription::class);
    }

    /** @return BelongsTo<SubscriptionPackage, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPackage::class, 'subscription_package_id');
    }

    public function stripeEmail(): ?string
    {
        return $this->users()
            ->wherePivot('role', OrganizationRole::Owner->value)
            ->orderBy('users.id')
            ->value('email');
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
     * @return HasMany<SystemAudit, $this>
     */
    public function audits(): HasMany
    {
        return $this->hasMany(SystemAudit::class);
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

    /**
     * Find an organization that has claimed a Google Workspace domain.
     *
     * @param  bool  $verifiedOnly  When true, only domains that passed TXT ownership verification match.
     */
    public static function findByGoogleDomain(string $domain, bool $verifiedOnly = true): ?self
    {
        return static::query()
            ->whereHas('googleDomains', function ($query) use ($domain, $verifiedOnly): void {
                $query->where('domain', strtolower($domain));

                if ($verifiedOnly) {
                    $query->whereNotNull('verified_at');
                }
            })
            ->first();
    }
}
