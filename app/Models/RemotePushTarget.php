<?php

namespace App\Models;

use App\Enums\RemotePushTargetStatus;
use Database\Factories\RemotePushTargetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $remote_push_id
 * @property int $hardware_id
 * @property RemotePushTargetStatus $status
 * @property Carbon|null $first_notified_at
 * @property Carbon|null $postpone_deadline_at
 * @property Carbon|null $scheduled_for
 * @property array<string, mixed>|null $result_json
 * @property string|null $error
 * @property Carbon|null $delivered_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read RemotePush $remotePush
 * @property-read Hardware $hardware
 */
#[Fillable([
    'remote_push_id',
    'hardware_id',
    'status',
    'first_notified_at',
    'postpone_deadline_at',
    'scheduled_for',
    'result_json',
    'error',
    'delivered_at',
    'completed_at',
])]
class RemotePushTarget extends Model
{
    /** @use HasFactory<RemotePushTargetFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RemotePushTargetStatus::class,
            'first_notified_at' => 'datetime',
            'postpone_deadline_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'result_json' => 'array',
            'delivered_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<RemotePush, $this>
     */
    public function remotePush(): BelongsTo
    {
        return $this->belongsTo(RemotePush::class);
    }

    /**
     * @return BelongsTo<Hardware, $this>
     */
    public function hardware(): BelongsTo
    {
        return $this->belongsTo(Hardware::class);
    }
}
