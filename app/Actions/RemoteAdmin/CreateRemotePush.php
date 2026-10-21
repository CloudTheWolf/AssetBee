<?php

namespace App\Actions\RemoteAdmin;

use App\Enums\RemotePushStatus;
use App\Enums\RemotePushTargetStatus;
use App\Enums\RemotePushType;
use App\Models\Organization;
use App\Models\RemotePush;
use App\Models\User;
use App\Support\SystemAuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateRemotePush
{
    public function __construct(private SystemAuditRecorder $auditRecorder) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Organization $organization, User $actor, array $input): RemotePush
    {
        $validated = Validator::make($input, [
            'type' => ['required', Rule::enum(RemotePushType::class)],
            'remote_package_ids' => ['nullable', 'array'],
            'remote_package_ids.*' => [
                'integer',
                Rule::exists('remote_packages', 'id')->where('organization_id', $organization->id),
            ],
            // Legacy single-package field still accepted.
            'remote_package_id' => [
                'nullable',
                'integer',
                Rule::exists('remote_packages', 'id')->where('organization_id', $organization->id),
            ],
            'device_group_id' => [
                'nullable',
                'integer',
                Rule::exists('device_groups', 'id')->where('organization_id', $organization->id),
            ],
            'hardware_ids' => ['nullable', 'array'],
            'hardware_ids.*' => [
                'integer',
                Rule::exists('hardwares', 'id')->where(fn ($query) => $query
                    ->where('organization_id', $organization->id)
                    ->whereNull('deleted_at')),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        $type = $validated['type'] instanceof RemotePushType
            ? $validated['type']
            : RemotePushType::from((string) $validated['type']);

        /** @var list<int> $packageIds */
        $packageIds = [];
        if (is_array($validated['remote_package_ids'] ?? null)) {
            $packageIds = array_values(array_unique(array_map(
                static fn (int|string $id): int => (int) $id,
                $validated['remote_package_ids'],
            )));
        } elseif (! empty($validated['remote_package_id'])) {
            $packageIds = [(int) $validated['remote_package_id']];
        }

        if ($type->requiresPackage() && $packageIds === []) {
            throw ValidationException::withMessages([
                'remote_package_ids' => __('Select at least one package for install pushes.'),
            ]);
        }

        if (! $type->requiresPackage()) {
            $packageIds = [];
        }

        /** @var list<int|string> $rawHardwareIds */
        $rawHardwareIds = is_array($validated['hardware_ids'] ?? null)
            ? $validated['hardware_ids']
            : [];

        /** @var list<int> $hardwareIds */
        $hardwareIds = array_values(array_unique(array_map(
            static fn (int|string $id): int => (int) $id,
            $rawHardwareIds,
        )));

        if (! empty($validated['device_group_id'])) {
            $groupIds = $organization->deviceGroups()
                ->whereKey($validated['device_group_id'])
                ->firstOrFail()
                ->hardwares()
                ->pluck('hardwares.id')
                ->map(static fn ($id): int => (int) $id)
                ->all();
            $hardwareIds = array_values(array_unique([...$hardwareIds, ...$groupIds]));
        }

        if ($hardwareIds === []) {
            throw ValidationException::withMessages([
                'hardware_ids' => __('Select at least one device or a group with members.'),
            ]);
        }

        $push = DB::transaction(function () use ($organization, $actor, $validated, $type, $hardwareIds, $packageIds): RemotePush {
            /** @var RemotePush $push */
            $push = $organization->remotePushes()->create([
                'created_by' => $actor->id,
                'type' => $type,
                'remote_package_id' => $packageIds[0] ?? null,
                'status' => RemotePushStatus::Open,
                'notes' => $validated['notes'] ?? null,
            ]);

            $sync = [];
            foreach ($packageIds as $position => $packageId) {
                $sync[$packageId] = ['position' => $position];
            }
            $push->packages()->sync($sync);

            foreach ($hardwareIds as $hardwareId) {
                $push->targets()->create([
                    'hardware_id' => $hardwareId,
                    'status' => RemotePushTargetStatus::Pending,
                ]);
            }

            return $push;
        });

        $this->auditRecorder->record('remote_push.created', $push, $organization->id);

        return $push->load(['packages', 'package', 'targets.hardware']);
    }
}
