<?php

namespace App\Providers;

use App\Http\Middleware\GuzzleExceptionMiddleware;
use GuzzleHttp\Client;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->VerifyDatabaseDriverAvailable("session","sessions");
        $this->VerifyDatabaseDriverAvailable("queue","queues");
        $this->VerifyDatabaseDriverAvailable("cache","cache");
        $this->VerifyDatabaseDriverAvailable("broadcast","broadcasts");
        $this->ForceFileSessionDriverOnInstallUpdate();
        Paginator::useTailwind();
    }

    /**
     * @param string $driver Driver we are checking
     * @param string $table Database Table Name
     * @return void
     */
    private function VerifyDatabaseDriverAvailable(string $driver, string $table) : void
    {
        if (Config::get($driver.'.driver') === 'database') {
            try {
                if (!Schema::hasTable($table)) {
                    Config::set($driver.'.driver', 'file'  );
                }
            } catch (\Exception) {
                Config::set($driver.'.driver', 'file');
            }
        }
    }

    private function ForceFileSessionDriverOnInstallUpdate()
    {
        if(Str::startsWith(request()->path(),['update','install']))
        {
            Config::set('session.driver', 'file'  );
        }
    }
}
