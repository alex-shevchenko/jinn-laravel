<?php

namespace Jinn\Laravel;

use Illuminate\Console\Command;
use Illuminate\Support\Composer;
use Jinn\Definition\DefinitionReader;
use Jinn\Generator\GeneratorConfig;
use Jinn\Laravel\Utils\Migrator;
use Jinn\Laravel\Generator\EntityGenerator;

class JinnGenerateCommand extends Command
{
    protected $signature = 'jinn';
    protected $description = 'Ask Jinn to (re)generate everything';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(Migrator $migrator, DefinitionReader $reader, GeneratorConfig $config, EntityGenerator $generator, Composer $composer)
    {
        $migrationsPath = $this->laravel->databasePath() . '/migrations/';

        $this->line('<info>Jinn started</info>');
        $this->line('<comment>Checking migrations</comment>');
        if ($migrator->hasPendingMigrations($migrationsPath)) {
            $this->error('Pending migrations detected. Invalid migrations may be generated, aborting.');
            $this->line('Run <info>php artisan migrate</info> first');
            return;
        }

        $this->line('<comment>Generating</comment>');

        $application = $reader->read($this->laravel->basePath() . '/' . config('jinn.definitions_folder', 'jinn/def'));

        $generatorParams = [
            'appFolder' => $this->laravel['path'],
            'appNamespace' => substr($this->laravel->getNamespace(), 0, -1),
            'migrationsPath' => $migrationsPath
        ];

        $config->output = [$this, 'line'];
        $generator->generate($application);

        $this->line('<info>Jinn done</info>');
        $this->line('<info>Executing migrations</info>');

        $this->call('migrate');

        $composer->dumpAutoloads();
    }
}
