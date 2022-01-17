<?php

namespace Jinn\Laravel;

use Illuminate\Support\ServiceProvider;
use Jinn\Generator\GeneratorConfig as BaseGeneratorConfig;
use Jinn\Laravel\Generator\GeneratorConfig;
use Jinn\Laravel\Utils\Migrator;

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
        $this->app->singleton(Migrator::class, function($app) {
            $repository = $app['migration.repository'];

            return new Migrator($repository, $app['db'], $app['files'], $app['events']);
        });
        $this->app->singleton(BaseGeneratorConfig::class, function($app) {
            return new GeneratorConfig();
        });
    }
}
