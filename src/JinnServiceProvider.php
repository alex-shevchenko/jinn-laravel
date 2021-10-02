<?php

namespace Jinn\Laravel;

use Illuminate\Support\ServiceProvider;

class JinnServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/jinn.php' => config_path('jinn.php'),
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                JinnGenerateCommand::class
            ]);
        }
    }

    public function register()
    {
        $this->app->singleton(JinnMigrator::class, function($app) {
            $repository = $app['migration.repository'];

            return new JinnMigrator($repository, $app['db'], $app['files'], $app['events']);
        });
    }
}
