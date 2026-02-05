<?php

namespace Iankibet\Streamline;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class StreamlineServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->registerSingleton();
        $this->registerConfig();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
        $this->registerCommands();
        $this->loadRoutesFrom(__DIR__.'/../routes/streamline.route.php');
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__.'/../config/streamline.php' => config_path('streamline.php'),
        ], ['laravel-streamline']);
    }

    protected function registerSingleton(): void
    {
        $this->app->singleton('streamline', function ($app) {
            return new StreamlineManager();
        });
        $this->app->alias('streamline', StreamlineManager::class);
    }

    protected function registerCommands(): void
    {
        $this->commands([
            Features\Commands\TestComponent::class,
            Features\Commands\MakeStream::class,
        ]);
    }
}
