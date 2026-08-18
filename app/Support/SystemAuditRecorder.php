<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\OrganizationApiKey;
use App\Models\SoftwareAssignment;
use App\Models\SubscriptionPackage;
use App\Models\SystemAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SystemAuditRecorder
{
    /**
     * @var list<string>
     */
    private const SUMMARY_ATTRIBUTES = ['name', 'email', 'domain', 'asset_tag'];

    /**
     * @var list<string>
     */
    private const USER_PROFILE_ATTRIBUTES = ['name', 'email', 'google_id'];

    public function recordModelEvent(string $event, Model $target): void
    {
        if (! $this->shouldRecordModel($target)) {
            return;
        }

        if ($target instanceof User && $event === 'updated') {
            $this->recordUserUpdate($target);

            return;
        }

        if ($target instanceof User && $event === 'created') {
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
        ?User $actor = null,
    ): void {
        $actor ??= Auth::user();
        $apiKey = $this->requestApiKey();

        if ($actor === null && $apiKey === null) {
            return;
        }

        if ($useCurrentOrganization) {
            $organizationId ??= CurrentOrganization::id() ?? $apiKey?->organization_id;
        }

        $targetId = $target->getKey();

        SystemAudit::query()->create([
            'actor_id' => $actor?->id,
            'actor_name' => $actor->name ?? $apiKey?->name,
            'organization_id' => $organizationId,
            'action' => $action,
            'target_type' => $target::class,
            'target_id' => is_numeric($targetId) ? (int) $targetId : null,
            'summary' => $this->summaryFor($target),
            'ip_address' => request()->ip(),
            'occurred_at' => now(),
        ]);
    }

    private function recordUserUpdate(User $user): void
    {
        if ($user->wasChanged('two_factor_confirmed_at')) {
            $this->record(
                $user->two_factor_confirmed_at !== null
                    ? 'auth.two_factor_enabled'
                    : 'auth.two_factor_disabled',
                $user,
            );

            return;
        }

        if ($user->wasChanged('password') && ! $user->wasChanged(self::USER_PROFILE_ATTRIBUTES)) {
            $this->record('auth.password_changed', $user);

            return;
        }

        if (! $user->wasChanged(self::USER_PROFILE_ATTRIBUTES)) {
            return;
        }

        $this->record('user.updated', $user);
    }

    private function shouldRecordModel(Model $target): bool
    {
        return ! $target instanceof SystemAudit
            && str_starts_with($target::class, 'App\\Models\\');
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

    private function summaryFor(Model $target): ?string
    {
        foreach (self::SUMMARY_ATTRIBUTES as $attribute) {
            $value = $target->getAttribute($attribute);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function requestApiKey(): ?OrganizationApiKey
    {
        $apiKey = request()->attributes->get('organizationApiKey');

        return $apiKey instanceof OrganizationApiKey ? $apiKey : null;
    }
}
