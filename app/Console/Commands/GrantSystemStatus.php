<?php

namespace App\Console\Commands;

use App\Enums\UserAccountType;
use App\Models\User;
use App\Support\CloudMode;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('system:grant {email : Email address of the dedicated System identity}')]
#[Description('Grant platform-wide System status to a dedicated user identity')]
class GrantSystemStatus extends Command
{
    public function handle(): int
    {
        if (! CloudMode::enabled()) {
            $this->components->error('System status cannot be granted in self-hosted mode.');

            return self::FAILURE;
        }

        $email = strtolower((string) $this->argument('email'));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user === null) {
            $this->components->error("No user exists with email {$email}.");

            return self::FAILURE;
        }

        if ($user->organizations()->exists()) {
            $this->components->error('Remove all organization memberships before granting System status.');

            return self::FAILURE;
        }

        if ($user->isSystem()) {
            $this->components->info("{$email} already has System status.");

            return self::SUCCESS;
        }

        $user->forceFill(['account_type' => UserAccountType::System])->save();
        $this->components->info("System status granted to {$email}.");

        return self::SUCCESS;
    }
}
