<?php

namespace App\Actions\Assets;

use App\Models\Hardware;

class DeleteHardware
{
    public function handle(Hardware $hardware): void
    {
        $hardware->delete();
    }
}
