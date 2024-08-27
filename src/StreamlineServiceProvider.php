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
        //
        $this->publishes([
            __DIR__.'/../config/streamline.php' => config_path('streamline.php'),
        ], ['laravel-streamline']);

    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
        $this->loadRoutesFrom(__DIR__.'/../routes/streamline.route.php');
    }
}
