<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RemoteAdmin\UpdateRemotePushTargetStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\RemotePushTarget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UpdateDroneCommandStatusController extends Controller
{
    public function __invoke(
        Request $request,
        int $targetId,
        UpdateRemotePushTargetStatus $updateRemotePushTargetStatus,
    ): JsonResponse {
        /** @var Organization $organization */
        $organization = $request->attributes->get('organization');

        $target = RemotePushTarget::query()
            ->with('remotePush')
            ->whereKey($targetId)
            ->whereHas('remotePush', fn ($query) => $query->where('organization_id', $organization->id))
            ->first();

        if ($target === null) {
            return response()->json(['message' => 'Command target not found.'], 404);
        }

        try {
            $target = $updateRemotePushTargetStatus->handle($target, $request->all());
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $exception->errors(),
            ], 422);
        }

        return response()->json([
            'data' => [
                'id' => $target->id,
                'status' => $target->status->value,
                'scheduledFor' => $target->scheduled_for?->toIso8601String(),
                'postponeDeadlineAt' => $target->postpone_deadline_at?->toIso8601String(),
                'firstNotifiedAt' => $target->first_notified_at?->toIso8601String(),
                'completedAt' => $target->completed_at?->toIso8601String(),
            ],
        ]);
    }
}
