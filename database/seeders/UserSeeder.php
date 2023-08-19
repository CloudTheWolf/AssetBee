<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;


class UserSeeder extends Seeder
{
    /**
     * Create our default user
     * @throws \Throwable
     */
    public function run(): void
    {
        if((new User)->whereId(1)->count() != 0) return;
        $user = new User();
        $user->name = "Administrator";
        $user->email = "admin@asset.bee";
        $user->password = bcrypt('assetbee');
        $user->email_verified_at = Carbon::now('utc');
        $user->role = 1;
        $user->disabled = 0;
        $user->saveOrFail();
    }
}
