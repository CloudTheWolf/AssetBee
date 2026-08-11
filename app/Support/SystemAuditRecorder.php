<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\SoftwareAssignment;
use App\Models\SubscriptionPackage;
use App\Models\SystemAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SystemAuditRecorder
{
    public function recordModelEvent(string $event, Model $target): void
    {
        if ($target instanceof SystemAudit) {
            return;
        }

        $this->record(
            Str::snake(class_basename($target)).'.'.$event,
            $target,
            $target instanceof SubscriptionPackage ? null : $this->organizationIdFor($target),
            ! ($target instanceof SubscriptionPackage),
        );
    }

    public function record(
        string $action,
        Model $target,
        ?int $organizationId = null,
        bool $useCurrentOrganization = true,
    ): void {
        /** @var User|null $actor */
        $actor = Auth::user();

        if ($actor === null || ! $actor->hasSystemAccess()) {
            return;
        }

        if ($useCurrentOrganization) {
            $organizationId ??= CurrentOrganization::id();
        }

        SystemAudit::query()->create([
            'actor_id' => $actor->id,
            'organization_id' => $organizationId,
            'action' => $action,
            'target_type' => $target::class,
            'target_id' => $target->getKey(),
            'ip_address' => request()->ip(),
            'occurred_at' => now(),
        ]);
    }

    private function organizationIdFor(Model $target): ?int
    {
        if ($target instanceof Organization) {
            return $target->id;
        }

        $organizationId = $target->getAttribute('organization_id');

        if (is_numeric($organizationId)) {
            return (int) $organizationId;
        }

        if ($target instanceof SoftwareAssignment) {
            $softwareOrganizationId = $target->software()->value('organization_id');

            return is_numeric($softwareOrganizationId) ? (int) $softwareOrganizationId : null;
        }

        return CurrentOrganization::id();
    }
}
