<?php

namespace App\Actions\RemoteAdmin;

use App\Models\RemotePush;
use App\Support\SystemAuditRecorder;

class DeleteRemotePush
{
    public function __construct(private SystemAuditRecorder $auditRecorder) {}

    public function handle(RemotePush $push): void
    {
        $organizationId = $push->organization_id;

        $this->auditRecorder->record('remote_push.deleted', $push, $organizationId);

        $push->delete();
    }
}
