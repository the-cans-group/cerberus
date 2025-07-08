<?php
namespace Cerberus;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Cerberus\Auth\CerberusGuard;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * CerberusServiceProvider
 *
 * This service provider is responsible for bootstrapping the Cerberus package.
 * It registers the Cerberus authentication guard and publishes configuration files.
 */
class CerberusProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @return void
     */
    public function boot(): void
    {
        // Load configuration files
        $this->publishes([
            __DIR__ . '/../config/cerberus.php' => config_path('cerberus.php'),
        ], 'cerberus-config');

        /*
        // Load the Cerberus migrations
        $this->publishes([
            __DIR__ . '/database/migrations/0000_00_00_000000_create_cerberus_initial_tables.php' =>
                database_path('migrations/' . date('Y_m_d_His', time()) . '_create_cerberus_initial_tables.php'),
        ], 'cerberus-migrations');
        */

        // Load the migrations for the Cerberus package
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        // Register the Cerberus service
        $this->app->singleton('cerberus', function () {
            return new CerberusService();
        });


        $this->app->extend(
            \Illuminate\Auth\Middleware\Authenticate::class,
            function ($middleware, $app) {
                return new \Cerberus\Http\Middleware\CerberusAuthenticate($app->make(AuthFactory::class));
            }
        );

    }

    /**
     * Register the Cerberus authentication guard.
     *
     * This method registers the CerberusGuard with the Laravel authentication system.
     * It also merges the package's configuration file with the application's configuration.
     *
     * @return void
     */
    public function register(): void
    {
        // Register the Cerberus authentication guard
        Auth::extend('cerberus', function ($app, $name, array $config) {
            return new CerberusGuard(
                $app['request'],
                new CerberusManager()
            );
        });

        // Merge the package's configuration with the application's configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../config/cerberus.php',
            'cerberus'
        );

        // Register the Cerberus Console commands
        $this->commands([
            Console\Commands\PruneCerberusSessions::class,
        ]);
    }

}
