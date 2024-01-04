<?php

namespace KalnaLab\Scrive;

use App\Events\FileCreated;
use App\Services\External\ExternalViewCollection;
use Illuminate\Support\ServiceProvider;
use KalnaLab\Scrive\Console\Install;
use KalnaLab\Scrive\Listeners\SendNewFile;
use KalnaLab\Scrive\Services\DocumentStore;
use KalnaLab\Scrive\Services\PersonProvider;

class ScriveServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
    ];

    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        /*
         * Optional methods to load your package assets
         */
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'scrive');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'scrive');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/routes.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/config.php' => config_path('scrive.php'),
            ], 'config');

            // Publishing the views.
            /*$this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/scrive'),
            ], 'views');*/

            // Publishing assets.
            /*$this->publishes([
                __DIR__.'/../resources/assets' => public_path('vendor/scrive'),
            ], 'assets');*/

            // Publishing the translation files.
            /*$this->publishes([
                __DIR__.'/../resources/lang' => resource_path('lang/vendor/scrive'),
            ], 'lang');*/

            // Registering package commands.
            $this->commands([
            ]);
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'scrive');

        // Register the main class to use with the facade
        $this->app->singleton('scrive', function () {
            return new \KalnaLab\Scrive\Facades\Scrive();
        });
    }
}
