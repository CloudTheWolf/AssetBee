<?php

namespace App\Console\Commands;

use App\Enums\UserAccountType;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('system:revoke {email : Email address of the System identity}')]
#[Description('Revoke platform-wide System status from a user identity')]
class RevokeSystemStatus extends Command
{
    public function handle(): int
    {
        $email = strtolower((string) $this->argument('email'));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user === null) {
            $this->components->error("No user exists with email {$email}.");

            return self::FAILURE;
        }

        if ($user->isCustomer()) {
            $this->components->info("{$email} already has Customer status.");

            return self::SUCCESS;
        }

        $user->forceFill(['account_type' => UserAccountType::Customer])->save();
        $this->components->info("System status revoked from {$email}.");

        return self::SUCCESS;
    }
}
