<?php

namespace App\Actions\RemoteAdmin;

use App\Enums\RemotePushStatus;
use App\Enums\RemotePushTargetStatus;
use App\Models\RemotePushTarget;
use App\Support\SystemAuditRecorder;
use Illuminate\Support\Facades\DB;

class ResetRemotePushTarget
{
    public function __construct(private SystemAuditRecorder $auditRecorder) {}

    public function handle(RemotePushTarget $target): RemotePushTarget
    {
        $push = $target->remotePush;

        return DB::transaction(function () use ($target, $push): RemotePushTarget {
            $target->update([
                'status' => RemotePushTargetStatus::Pending,
                'first_notified_at' => null,
                'postpone_deadline_at' => null,
                'scheduled_for' => null,
                'result_json' => null,
                'error' => null,
                'delivered_at' => null,
                'completed_at' => null,
            ]);

            if ($push->status !== RemotePushStatus::Open) {
                $push->update(['status' => RemotePushStatus::Open]);
            }

            $this->auditRecorder->record('remote_push_target.reset', $target->fresh(), $push->organization_id);

            return $target->refresh();
        });
    }
}
