<?php

namespace App\Models;

use Database\Factories\SoftwareKeyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $software_id
 * @property string $value
 * @property string|null $label
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'software_id',
    'value',
    'label',
])]
class SoftwareKey extends Model
{
    /** @use HasFactory<SoftwareKeyFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<Software, $this>
     */
    public function software(): BelongsTo
    {
        return $this->belongsTo(Software::class);
    }

    /**
     * @return HasOne<SoftwareAssignment, $this>
     */
    public function assignment(): HasOne
    {
        return $this->hasOne(SoftwareAssignment::class);
    }

    public function isAssigned(): bool
    {
        if ($this->relationLoaded('assignment')) {
            return $this->assignment !== null;
        }

        return $this->assignment()->exists();
    }

    public function maskedValue(): string
    {
        $value = $this->value;

        if (strlen($value) <= 4) {
            return str_repeat('•', max(strlen($value), 4));
        }

        return str_repeat('•', max(strlen($value) - 4, 4)).substr($value, -4);
    }
}
