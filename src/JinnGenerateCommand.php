<?php


namespace Jinn\Laravel;

use Illuminate\Console\Command;
use Jinn\JinnDefinitionReader;

class JinnGenerateCommand extends Command
{
    protected $signature = 'jinn';
    protected $description = 'Ask Jinn to (re)generate everything';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(JinnDefinitionReader $reader, JinnEntityGenerator $generator)
    {
        $entities = $reader->read($this->laravel->basePath() . '/' . config('jinn.definitions_folder'));
        $generator->setBase(
            $this->laravel->basePath(),
            $this->laravel['path'],
            substr($this->laravel->getNamespace(), 0, -1),
            $this->laravel->databasePath()
        );
        $generator->generateEntities($entities->entities());
    }
}
