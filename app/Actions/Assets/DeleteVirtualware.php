<?php

namespace App\Actions\Assets;

use App\Models\Virtualware;

class DeleteVirtualware
{
    public function handle(Virtualware $virtualware): void
    {
        $virtualware->delete();
    }
}
