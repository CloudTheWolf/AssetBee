<?php

namespace App\Providers;

use App\Contracts\Cloud\DiscoversCloudVirtualMachines;
use App\Contracts\DomainDnsLookup;
use App\Models\Organization;
use App\Models\User;
use App\Services\Cloud\AwsEc2DiscoveryService;
use App\Services\Cloud\CloudVirtualMachineDiscoveryManager;
use App\Services\PhpDomainDnsLookup;
use App\Support\OrganizationSubscriptionLimits;
use App\Support\SailRuntime;
use App\Support\SystemAuditRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Cashier\Cashier;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DomainDnsLookup::class, PhpDomainDnsLookup::class);

        $this->app->tag([
            AwsEc2DiscoveryService::class,
        ], DiscoversCloudVirtualMachines::class);

        $this->app->singleton(CloudVirtualMachineDiscoveryManager::class, function ($app): CloudVirtualMachineDiscoveryManager {
            return new CloudVirtualMachineDiscoveryManager(
                $app->tagged(DiscoversCloudVirtualMachines::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Cashier::useCustomerModel(Organization::class);

        $this->configureDefaults();
        $this->configureSailRuntime();
        $this->configureEmailVerification();
        $this->registerOrganizationSubscriptionLimits();
        $this->registerAuditListeners();

        // Keep Livewire single-file components ASCII-only in filenames.
        config(['livewire.make_command.emoji' => false]);
    }

    /**
     * Send a welcome email that asks new users to verify their address.
     */
    private function configureEmailVerification(): void
    {
        VerifyEmail::toMailUsing(function (object $notifiable, string $url): MailMessage {
            $appName = config('app.name');

            return (new MailMessage)
                ->subject(__('Welcome to :app — verify your email', ['app' => $appName]))
                ->markdown('mail.welcome-verify-email', [
                    'url' => $url,
                    'name' => $notifiable->name ?? __('there'),
                    'appName' => $appName,
                ]);
        });
    }

    /**
     * Prefer a compiled-view path that supports utime() under Sail/WSL mounts.
     */
    private function configureSailRuntime(): void
    {
        if (! (bool) config('app.sail')) {
            return;
        }

        SailRuntime::ensureCompiledViewPath();
    }

    private function registerOrganizationSubscriptionLimits(): void
    {
        Event::listen('eloquent.creating: *', function (string $eventName, array $models): void {
            $model = $models[0] ?? null;

            if ($model instanceof Model) {
                app(OrganizationSubscriptionLimits::class)->assertCanCreate($model);
            }
        });
    }

    private function registerAuditListeners(): void
    {
        foreach (['created', 'updated', 'deleted'] as $event) {
            Event::listen("eloquent.{$event}: *", function (string $eventName, array $models) use ($event): void {
                $model = $models[0] ?? null;

                if ($model instanceof Model) {
                    app(SystemAuditRecorder::class)->recordModelEvent($event, $model);
                }
            });
        }

        Event::listen(Login::class, fn (Login $event) => $this->recordAuthEvent('auth.login', $event->user));
        Event::listen(Logout::class, fn (Logout $event) => $this->recordAuthEvent('auth.logout', $event->user));
        Event::listen(PasswordReset::class, fn (PasswordReset $event) => $this->recordAuthEvent('auth.password_reset', $event->user));
        Event::listen(Verified::class, fn (Verified $event) => $this->recordAuthEvent('auth.email_verified', $event->user));
        Event::listen(Failed::class, function (Failed $event): void {
            if ($event->user instanceof User) {
                $this->recordAuthEvent('auth.failed', $event->user);
            }
        });
    }

    private function recordAuthEvent(string $action, mixed $user): void
    {
        if (! $user instanceof User) {
            return;
        }

        app(SystemAuditRecorder::class)->record($action, $user, actor: $user);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
