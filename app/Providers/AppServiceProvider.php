<?php

namespace App\Providers;

use App\Contracts\Cloud\DiscoversCloudVirtualMachines;
use App\Services\Cloud\AwsEc2DiscoveryService;
use App\Services\Cloud\CloudVirtualMachineDiscoveryManager;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
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
        $this->configureDefaults();

        // Keep Livewire single-file components ASCII-only in filenames.
        config(['livewire.make_command.emoji' => false]);
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
