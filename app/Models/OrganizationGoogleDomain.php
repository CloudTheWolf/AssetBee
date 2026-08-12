<?php

namespace App\Models;

use Database\Factories\OrganizationGoogleDomainFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $domain
 * @property string $verification_token
 * @property Carbon|null $verified_at
 * @property Carbon|null $verification_last_checked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id',
    'domain',
    'verification_token',
    'verified_at',
    'verification_last_checked_at',
])]
class OrganizationGoogleDomain extends Model
{
    public const TXT_RECORD_PREFIX = 'assetbee-domain-verification=';

    /** @use HasFactory<OrganizationGoogleDomainFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'verification_last_checked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (OrganizationGoogleDomain $domain): void {
            if (blank($domain->verification_token)) {
                $domain->verification_token = static::generateVerificationToken();
            }
        });
    }

    public static function generateVerificationToken(): string
    {
        return Str::lower(Str::random(40));
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function txtRecordValue(): string
    {
        return self::TXT_RECORD_PREFIX.$this->verification_token;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->whereNotNull('verified_at');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
