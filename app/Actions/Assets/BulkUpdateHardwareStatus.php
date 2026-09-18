<?php

namespace App\Actions\Assets;

use App\Enums\HardwareStatus;
use App\Models\Hardware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BulkUpdateHardwareStatus
{
    /**
     * @param  Collection<int, Hardware>  $hardwares
     * @return array{updated: int}
     *
     * @throws ValidationException
     */
    public function handle(Collection $hardwares, string $status): array
    {
        if ($hardwares->isEmpty()) {
            throw ValidationException::withMessages([
                'selected' => __('Select at least one device.'),
            ]);
        }

        $validated = Validator::make(
            ['status' => $status],
            ['status' => ['required', Rule::enum(HardwareStatus::class)]],
        )->validate();

        $status = HardwareStatus::from($validated['status']);

        foreach ($hardwares as $hardware) {
            $hardware->forceFill([
                'status' => $status,
            ])->save();
        }

        return [
            'updated' => $hardwares->count(),
        ];
    }
}
