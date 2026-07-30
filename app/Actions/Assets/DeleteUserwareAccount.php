<?php

namespace App\Actions\Assets;

use App\Models\UserwareAccount;

class DeleteUserwareAccount
{
    public function handle(UserwareAccount $account): void
    {
        $account->delete();
    }
}
