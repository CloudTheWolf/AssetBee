<?php

namespace App\Actions\RemoteAdmin;

use App\Enums\RemotePushStatus;
use App\Enums\RemotePushTargetStatus;
use App\Models\RemotePush;
use App\Support\SystemAuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelRemotePush
{
    public function __construct(private SystemAuditRecorder $auditRecorder) {}

    /**
     * @throws ValidationException
     */
    public function handle(RemotePush $push): RemotePush
    {
        if ($push->status === RemotePushStatus::Cancelled) {
            throw ValidationException::withMessages([
                'status' => __('This push is already cancelled.'),
            ]);
        }

        DB::transaction(function () use ($push): void {
            $push->update(['status' => RemotePushStatus::Cancelled]);
            $push->targets()
                ->whereIn('status', array_map(
                    static fn (RemotePushTargetStatus $status): string => $status->value,
                    RemotePushTargetStatus::incompleteCases(),
                ))
                ->update([
                    'status' => RemotePushTargetStatus::Expired,
                    'completed_at' => now(),
                    'error' => 'Push cancelled by administrator.',
                ]);
        });

        $this->auditRecorder->record('remote_push.cancelled', $push, $push->organization_id);

        return $push->refresh();
    }
}
