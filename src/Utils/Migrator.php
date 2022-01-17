<?php


namespace Jinn\Laravel\Utils;


use Illuminate\Database\Migrations\Migrator as BaseMigrator;

class Migrator extends BaseMigrator
{
    public function hasPendingMigrations($path): bool
    {
        $files = $this->getMigrationFiles([$path]);

        $migrations = $this->pendingMigrations($files, $this->repository->getRan());

        return count($migrations) > 0;
    }
}
