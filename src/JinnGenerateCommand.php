<?php

namespace Jinn\Laravel;

use Illuminate\Console\Command;
use Illuminate\Support\Composer;
use Jinn\Definition\DefinitionReader;
use Jinn\Laravel\Generator\Migrator;
use Jinn\Laravel\Generator\ModelGenerator;

class JinnGenerateCommand extends Command
{
    protected $signature = 'jinn';
    protected $description = 'Ask Jinn to (re)generate everything';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(Migrator $migrator, DefinitionReader $reader, Composer $composer)
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

        $application = $reader->read($this->laravel->basePath() . '/' . config('jinn.definitions_folder'));

        $generatorParams = [
            'appFolder' => $this->laravel['path'],
            'appNamespace' => substr($this->laravel->getNamespace(), 0, -1),
            'migrationsPath' => $migrationsPath
        ];
        $generator = new ModelGenerator($generatorParams, $this->getOutput());

        $generator->generate($application);

        $this->line('<info>Jinn done</info>');
        $this->line('<info>Executing migrations</info>');

        $this->call('migrate');

        $composer->dumpAutoloads();
    }
}
