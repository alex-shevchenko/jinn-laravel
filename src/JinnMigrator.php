<?php


namespace Jinn\Laravel;


use Illuminate\Database\Migrations\Migrator;

class JinnMigrator extends Migrator
{
    public function hasPendingMigrations($path): bool
    {
        $files = $this->getMigrationFiles([$path]);

        $migrations = $this->pendingMigrations($files, $this->repository->getRan());

        return count($migrations) > 0;
    }
}
