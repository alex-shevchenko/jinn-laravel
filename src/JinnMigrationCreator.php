<?php


namespace Jinn\Laravel;


use Illuminate\Database\Migrations\MigrationCreator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Jinn\Models\Entity;
use Jinn\Models\Relation;
use Nette\PhpGenerator\Dumper;
use Nette\PhpGenerator\PhpFile;

class JinnMigrationCreator extends MigrationCreator
{
    public function __construct()
    {
        parent::__construct(File::getFacadeRoot(), '');
    }

    private function tableName(Entity $entity)
    {
        $tableName = $entity->name;
        if (!$entity->isPivot)
            $tableName = Str::pluralStudly($tableName);
        return Str::snake($tableName);
    }

    private function writeMigrationFile($migrationName, $upCode, $downCode, $path)
    {
        $migrationFile = new PhpFile();
        $migrationFile->addUse('Illuminate\\Database\\Migrations\\Migration');
        $migrationFile->addUse('Illuminate\\Database\\Schema\\Blueprint');
        $migrationFile->addUse('Illuminate\\Support\\Facades\\Schema');

        $migrationClass = $migrationFile->addClass(Str::studly($migrationName));
        $migrationClass->setExtends('Illuminate\\Database\\Migrations\\Migration');

        $upMethod = $migrationClass->addMethod('up');
        $upMethod->setPublic();
        $upMethod->setBody($upCode);

        $downMethod = $migrationClass->addMethod('down');
        $downMethod->setPublic();
        $downMethod->setBody($downCode);

        $filename = $this->getPath($migrationName, $path);
        JinnFileWriter::writePhpFile($filename, $migrationFile);
    }

    public function createTableMigration(Entity $entity, $path)
    {
        $tableName = $this->tableName($entity);
        $migrationName = 'create_' . $tableName . '_table';
        $this->ensureMigrationDoesntAlreadyExist($migrationName, $path);

        $dumper = new Dumper();

        $upCode = "Schema::create('$tableName', function (Blueprint \$table) {\n";
        foreach ($entity->fields() as $field) {
            if ($field->primary) {
                $upCode .= "\t\$table->id();\n";
            } else {
                $type = Types::toEloquentType($field->type);
                $fieldName = Str::snake($field->name);
                if (!$type) throw new \LogicException("Unsupported field type {$field->type}");
                $upCode .= "\t\$table->$type('$fieldName'" . ($field->length ? ", {$field->length}" : '') . ")";
                if (!$field->required) $upCode .= '->nullable()';
                if (!is_null($field->default)) $upCode .= '->default(' . $dumper->dump($field->default) . ')';
                $upCode .= ";\n";
            }
        }
        $upCode .= "\t\$table->timestamps();\n";

        foreach ($entity->indexes() as $index) {
            $upCode .= "\t\$table->" . ($index->isUnique ? 'unique' : 'index') .
                "(['" . implode("', '", array_map([Str::class, 'snake'], $index->columns) ). "'], '{$index->name}');\n";
        }

        $upCode .= '});';

        $downCode = "Schema::dropIfExists('$tableName');\n";

        $migrationFile = $this->writeMigrationFile($migrationName, $upCode, $downCode, $path);
    }

    public function createForeignKeysMigration(Entity $entity, $path)
    {
        $tableName = $this->tableName($entity);
        $migrationName = 'create_' . $tableName . '_foreign_keys';
        $this->ensureMigrationDoesntAlreadyExist($migrationName, $path);

        $upCode = "Schema::table('$tableName', function (Blueprint \$table) {\n";
        $downCode = "Schema::table('$tableName', function (Blueprint \$table) {\n";

        $hasForeignKeys = false;

        foreach ($entity->relations() as $relation) {
            if ($relation->type == Relation::MANY_TO_ONE) {
                $hasForeignKeys = true;
                $field = Str::snake($relation->field ?? ($relation->entityName . 'Id'));
                $upCode .= "\t\$table->foreign('" . $field . "')" .
                    "->references('id')->on('" . $this->tableName($relation->entity) . "');\n";

                $downCode .= "\t\$table->dropForeign('" . $tableName . '_' . $field . '_foreign' . "');\n";
            }
        }

        if (!$hasForeignKeys) return;

        $upCode .= '});';
        $downCode .= '});';

        $this->writeMigrationFile($migrationName, $upCode, $downCode, $path);
    }

    protected function getDatePrefix()
    {
        return parent::getDatePrefix() . gettimeofday()['usec'];
    }
}
