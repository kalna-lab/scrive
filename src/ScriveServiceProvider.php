<?php

declare(strict_types=1);

namespace KalnaLab\Scrive;

use Illuminate\Support\ServiceProvider;

class ScriveServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/config.php' => config_path('scrive.php'),
            ], 'scrive-config');
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'scrive');

        // The `scrive` container binding powers the Scrive facade.
        // Returning a fresh Scrive instance per call avoids sharing mutable
        // state (endpoints, bodies) between unrelated callers.
        $this->app->bind('scrive', fn () => new Scrive);
    }
}
