<?php

namespace App\Actions\RemoteAdmin;

use App\Enums\RemotePushStatus;
use App\Enums\RemotePushTargetStatus;
use App\Models\RemotePushTarget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateRemotePushTargetStatus
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(RemotePushTarget $target, array $input): RemotePushTarget
    {
        $validated = Validator::make($input, [
            'status' => ['required', Rule::enum(RemotePushTargetStatus::class)],
            'scheduledFor' => ['nullable', 'date'],
            'result' => ['nullable', 'array'],
            'error' => ['nullable', 'string', 'max:4000'],
        ])->validate();

        $newStatus = RemotePushTargetStatus::from($validated['status']);
        $push = $target->remotePush;

        if ($push->status === RemotePushStatus::Cancelled) {
            throw ValidationException::withMessages([
                'status' => __('This push was cancelled.'),
            ]);
        }

        if ($target->status->isTerminal()) {
            throw ValidationException::withMessages([
                'status' => __('This target is already finished.'),
            ]);
        }

        $now = now();
        $attributes = ['status' => $newStatus];

        if ($target->first_notified_at === null && in_array($newStatus, [
            RemotePushTargetStatus::Delivered,
            RemotePushTargetStatus::AwaitingUser,
            RemotePushTargetStatus::Postponed,
            RemotePushTargetStatus::Scheduled,
            RemotePushTargetStatus::Running,
        ], true)) {
            $attributes['first_notified_at'] = $now;
            $attributes['postpone_deadline_at'] = $now->copy()->addDay();
        }

        $deadline = $target->postpone_deadline_at
            ?? ($attributes['postpone_deadline_at'] ?? null);

        if (in_array($newStatus, [
            RemotePushTargetStatus::Postponed,
            RemotePushTargetStatus::Scheduled,
        ], true)) {
            if ($deadline === null) {
                throw ValidationException::withMessages([
                    'scheduledFor' => __('Postpone deadline has not been established.'),
                ]);
            }

            $scheduledFor = isset($validated['scheduledFor'])
                ? Carbon::parse($validated['scheduledFor'])
                : null;

            if ($scheduledFor === null) {
                throw ValidationException::withMessages([
                    'scheduledFor' => __('A schedule time is required.'),
                ]);
            }

            if ($scheduledFor->greaterThan($deadline)) {
                throw ValidationException::withMessages([
                    'scheduledFor' => __('Installs cannot be postponed more than 24 hours from first notice.'),
                ]);
            }

            if ($scheduledFor->lessThan($now->copy()->subMinute())) {
                throw ValidationException::withMessages([
                    'scheduledFor' => __('Schedule time must be in the future.'),
                ]);
            }

            $attributes['scheduled_for'] = $scheduledFor;
        }

        if ($newStatus === RemotePushTargetStatus::Delivered || $newStatus === RemotePushTargetStatus::AwaitingUser) {
            $attributes['delivered_at'] = $target->delivered_at ?? $now;
        }

        if ($newStatus->isTerminal()) {
            $attributes['completed_at'] = $now;
            $attributes['result_json'] = $validated['result'] ?? null;
            $attributes['error'] = $validated['error'] ?? null;
            $attributes['scheduled_for'] = null;
        }

        if ($newStatus === RemotePushTargetStatus::Running) {
            $attributes['error'] = null;
        }

        return DB::transaction(function () use ($target, $attributes): RemotePushTarget {
            $target->update($attributes);
            $target->remotePush->refreshCompletionStatus();

            return $target->refresh();
        });
    }
}
