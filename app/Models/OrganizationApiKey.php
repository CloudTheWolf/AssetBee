<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $key_prefix
 * @property string $key_hash
 * @property Carbon|null $last_used_at
 * @property Carbon|null $revoked_at
 */
#[Fillable([
    'organization_id',
    'name',
    'key_prefix',
    'key_hash',
    'last_used_at',
    'revoked_at',
])]
class OrganizationApiKey extends Model
{
    /** @var list<string> */
    protected $hidden = ['key_hash'];

    /**
     * @return array{0: self, 1: string}
     */
    public static function issue(Organization $organization, string $name): array
    {
        $plainTextKey = 'abk_'.Str::random(64);

        $apiKey = $organization->apiKeys()->create([
            'name' => $name,
            'key_prefix' => substr($plainTextKey, 0, 12),
            'key_hash' => hash('sha256', $plainTextKey),
        ]);

        return [$apiKey, $plainTextKey];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
