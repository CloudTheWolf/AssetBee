<?php

namespace App\Actions\Assets;

use App\Models\Software;

class DeleteSoftware
{
    public function handle(Software $software): void
    {
        $software->delete();
    }
}
