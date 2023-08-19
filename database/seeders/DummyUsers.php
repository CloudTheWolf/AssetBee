<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Throwable;

class DummyUsers extends Seeder
{
    /**
     * Run the database seeds.
     * @throws Throwable
     */
    public function run(): void
    {
        $user = new User();
        $user->name = Str::random(10);
        $user->email = Str::random(10).'@gmail.com';
        $user->password = bcrypt(Str::random(10));
        $user->email_verified_at = Carbon::now('utc');
        $user->role = 3;
        $user->disabled = 0;
        $user->saveOrFail();
    }
}
