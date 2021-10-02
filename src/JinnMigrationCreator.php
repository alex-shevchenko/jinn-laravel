<?php


namespace Jinn\Laravel;


use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index as DbIndex;
use Illuminate\Database\Migrations\MigrationCreator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Jinn\Database\ColumnDiff;
use Jinn\Database\DatabaseComparer;
use Jinn\Database\IndexDiff;
use Jinn\Models\Entity;
use Jinn\Models\Field;
use Jinn\Models\Index;
use Jinn\Models\Relation;
use Nette\PhpGenerator\Dumper;
use Nette\PhpGenerator\PhpFile;

class JinnMigrationCreator extends MigrationCreator
{
    private DatabaseComparer $databaseComparer;
    private Dumper $dumper;

    /**
     * JinnMigrationCreator constructor.
     * @throws \Doctrine\DBAL\Exception
     */
    public function __construct()
    {
        parent::__construct(File::getFacadeRoot(), '');
        $connectionName = config('database.default');
        $connectionParams = config('database.connections.' . $connectionName);
        $connectionParams = $this->laravelToDoctrineParams($connectionParams);

        $this->databaseComparer = new DatabaseComparer($connectionParams, [Str::class, 'snake'], [Str::class, 'camel']);
        $this->dumper = new Dumper();
    }

    private function laravelToDoctrineParams($connectionParams) {
        if (!empty($connectionParams['driver'])) $connectionParams['driver'] = 'pdo_' . $connectionParams['driver'];
        if (!empty($connectionParams['url'])) $connectionParams['url'] = 'pdo_' . $connectionParams['url'];
        if (!empty($connectionParams['username'])) $connectionParams['user'] = $connectionParams['username'];
        if (!empty($connectionParams['database'])) $connectionParams['dbname'] = $connectionParams['database'];

        return $connectionParams;
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

        return $migrationName;
    }

    /**
     * @param Field|Column $column
     * @return string
     */
    private function columnCode($column): string
    {
        if ($column instanceof Field) {
            $typeName = $column->type;
            $columnName = Str::snake($column->name);
            $length = $column->length;
            $required = $column->required;
            $default = $column->default;
        } elseif ($column instanceof Column) {
            $typeName = $column->getType()->getName();
            $columnName = $column->getName();
            $length = $column->getLength();
            $required = $column->getNotnull();
            $default = $column->getDefault();
        }

        $type = Types::toEloquentType($typeName);

        if (!$type) throw new \LogicException("Unsupported field type $typeName");
        $code = "\t\$table->$type('$columnName'" . ($length ? ", $length" : '') . ")";
        $code .= '->nullable(' . $this->dumper->dump(!$required) . ')';
        $code .= '->default(' . $this->dumper->dump($default) . ')';

        return $code;
    }

    /**
     * @param Index|DbIndex $index
     * @return array
     */
    private function indexCode($index): array
    {
        if ($index instanceof Index) {
            $isUnique = $index->isUnique;
            $name = $index->name;
            $columns = array_map([Str::class, 'snake'], $index->columns);
        } elseif ($index instanceof DbIndex) {
            $isUnique = $index->isUnique();
            $name = $index->getName();
            $columns = $index->getColumns();
        }
        return ["\t\$table->" . ($isUnique ? 'unique' : 'index') .
            "(['" . implode("', '", $columns). "'], '$name');\n",
            "\t\$table->drop" . ($isUnique ? 'Unique' : 'Index') . "('$name');\n"
            ];
    }

    /**
     * @param Index|DbIndex $index
     * @return string
     */
    private function dropIndexCode($index): string
    {
        if ($index instanceof Index) {
            $name = $index->name;
        }
    }

    /**
     * @param Entity $entity
     * @param bool $newTable
     * @param string $upCode
     * @param string $downCode
     * @return bool
     * @throws \Doctrine\DBAL\Exception
     */
    protected function createColumnsMigration(Entity $entity, bool $newTable, string &$upCode, string &$downCode): bool
    {
        $tableName = $this->tableName($entity);

        if (!$newTable) {
            $changes = $this->databaseComparer->compareTableColumns($entity, $tableName);

            foreach ($changes as $i => $diff) {
                if ($diff->operation == ColumnDiff::OP_REMOVE) {
                    if ($diff->column->getName() == 'created_at') unset($changes[$i]);
                    if ($diff->column->getName() == 'updated_at') unset($changes[$i]);
                    if (!$diff->column->getNotnull()) unset($changes[$i]);
                }
            }

            if (!$changes) return false;
        } else {
            $changes = array_map(function(Field $field) { return new ColumnDiff($field); }, $entity->fields());
        }

        foreach ($changes as $diff) {
            if ($diff->operation == ColumnDiff::OP_REMOVE) {
                $downCode .= $this->columnCode($diff->column);
                $diff->column->setNotnull(false);
                $upCode .= $this->columnCode($diff->column);
            } else {
                if ($diff->field->primary) {
                    $upCode .= "\t\$table->id()";
                } else {
                    $upCode .= $this->columnCode($diff->field);
                    if ($diff->operation == ColumnDiff::OP_CHANGE) {
                        $downCode .= $this->columnCode($diff->column);
                    } elseif (!$newTable) {
                        $columnName = Str::snake($diff->field->name);
                        $downCode .= "\t\$table->dropColumn('$columnName')";
                    }
                }
            }
            if ($diff->operation != ColumnDiff::OP_ADD) {
                $upCode .= '->change()';
                $downCode .= '->change()';
            }

            $upCode .= ";\n";
            $downCode .= ";\n";
        }

        if ($newTable)
            $upCode .= "\t\$table->timestamps();\n";

        return true;
    }

    /**
     * @param Entity $entity
     * @param bool $newTable
     * @param string $upCode
     * @param string $downCode
     * @return bool
     * @throws \Doctrine\DBAL\Exception
     */
    protected function createIndexesMigration(Entity $entity, bool $newTable, string &$upCode, string &$downCode): bool
    {
        $tableName = $this->tableName($entity);

        if (!$newTable) {
            $changes = $this->databaseComparer->compareTableIndexes($entity, $tableName);
            if (!$changes) return false;
        } else {
            $changes = array_map(function (Index $index) { return new IndexDiff($index); }, $entity->indexes());
        }

        foreach ($changes as $diff) {
            if ($diff->operation != IndexDiff::OP_ADD) {
                list($create1, $drop1) = $this->indexCode($diff->dbIndex);
                $upCode .= $drop1;
            }
            if ($diff->operation != IndexDiff::OP_REMOVE) {
                list($create2, $drop2) = $this->indexCode($diff->index);
                $upCode .= $create2;
                if (!$newTable) $downCode .= $drop2;
            }
            if ($diff->operation != IndexDiff::OP_ADD) {
                if (!$newTable) $downCode .= $create1;
            }
        }
        return true;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function createStructureMigration(Entity $entity, string $path): ?string
    {
        $tableName = $this->tableName($entity);
        $newTable = !$this->databaseComparer->tableExists($tableName);

        $migrationName = ($newTable ? 'create' : 'update') . '_' . $tableName . '_table_' . time();
        $this->ensureMigrationDoesntAlreadyExist($migrationName, $path);

        $upCode = "Schema::" . ($newTable ? 'create' : 'table') . "('$tableName', function (Blueprint \$table) {\n";
        $downCode = '';
        if (!$newTable) {
            $downCode = $upCode;
        }

        $changed = $this->createColumnsMigration($entity, $newTable, $upCode, $downCode);

        $changed = $changed || $this->createIndexesMigration($entity, $newTable, $upCode, $downCode);

        $upCode .= '});';
        if (!$newTable)
            $downCode .= "});";
        else
            $downCode = "Schema::dropIfExists('$tableName');\n";

        if ($changed) {
            return $this->writeMigrationFile($migrationName, $upCode, $downCode, $path);
        } else {
            return null;
        }
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
