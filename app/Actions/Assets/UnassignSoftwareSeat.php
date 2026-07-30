<?php

namespace App\Actions\Assets;

use App\Models\SoftwareAssignment;

class UnassignSoftwareSeat
{
    public function handle(SoftwareAssignment $assignment): void
    {
        $assignment->delete();
    }
}
