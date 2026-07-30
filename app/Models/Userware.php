<?php

namespace App\Models;

use App\Enums\UserwareStatus;
use Database\Factories\UserwareFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $email
 * @property string|null $employee_id
 * @property string|null $department
 * @property UserwareStatus $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['organization_id', 'name', 'email', 'employee_id', 'department', 'status', 'notes'])]
class Userware extends Model
{
    /** @use HasFactory<UserwareFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'userwares';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => UserwareStatus::class,
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
     * @return HasMany<Hardware, $this>
     */
    public function hardwares(): HasMany
    {
        return $this->hasMany(Hardware::class, 'assigned_userware_id');
    }

    /**
     * @return HasMany<Virtualware, $this>
     */
    public function virtualwares(): HasMany
    {
        return $this->hasMany(Virtualware::class, 'assigned_userware_id');
    }

    /**
     * @return HasMany<SoftwareAssignment, $this>
     */
    public function softwareAssignments(): HasMany
    {
        return $this->hasMany(SoftwareAssignment::class);
    }
}
