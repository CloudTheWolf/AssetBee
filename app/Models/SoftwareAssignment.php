<?php

namespace App\Models;

use Database\Factories\SoftwareAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $software_id
 * @property int $userware_id
 * @property Carbon $assigned_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['software_id', 'userware_id', 'assigned_at', 'notes'])]
class SoftwareAssignment extends Model
{
    /** @use HasFactory<SoftwareAssignmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
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
     * @return BelongsTo<Userware, $this>
     */
    public function userware(): BelongsTo
    {
        return $this->belongsTo(Userware::class);
    }
}
