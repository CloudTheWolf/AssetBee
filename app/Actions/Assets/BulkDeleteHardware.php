<?php

namespace App\Actions\Assets;

use App\Models\Hardware;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BulkDeleteHardware
{
    public function __construct(private DeleteHardware $deleteHardware) {}

    /**
     * @param  Collection<int, Hardware>  $hardwares
     * @return array{deleted: int}
     *
     * @throws ValidationException
     */
    public function handle(Collection $hardwares): array
    {
        if ($hardwares->isEmpty()) {
            throw ValidationException::withMessages([
                'selected' => __('Select at least one device.'),
            ]);
        }

        foreach ($hardwares as $hardware) {
            $this->deleteHardware->handle($hardware);
        }

        return [
            'deleted' => $hardwares->count(),
        ];
    }
}
