<?php

namespace App\Actions\Assets;

use App\Enums\HardwareStatus;
use App\Models\Hardware;
use App\Models\Userware;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BulkAssignHardware
{
    public function __construct(private AssignHardware $assignHardware) {}

    /**
     * @param  Collection<int, Hardware>  $hardwares
     * @return array{assigned: int, skipped: int}
     *
     * @throws ValidationException
     */
    public function handle(Collection $hardwares, ?Userware $userware): array
    {
        if ($hardwares->isEmpty()) {
            throw ValidationException::withMessages([
                'selected' => __('Select at least one device.'),
            ]);
        }

        $assigned = 0;
        $skipped = 0;

        foreach ($hardwares as $hardware) {
            if ($userware !== null && $hardware->status === HardwareStatus::Retired) {
                $skipped++;

                continue;
            }

            $this->assignHardware->handle($hardware, $userware);
            $assigned++;
        }

        return [
            'assigned' => $assigned,
            'skipped' => $skipped,
        ];
    }
}
