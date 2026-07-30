<?php

namespace App\Actions\Assets;

use App\Models\Userware;

class DeleteUserware
{
    public function handle(Userware $userware): void
    {
        $userware->delete();
    }
}
