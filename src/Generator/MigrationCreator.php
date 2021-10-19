<?php


namespace Jinn\Laravel\Generator;


use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index as DbIndex;
use Illuminate\Database\Migrations\MigrationCreator as BaseMigrationCreator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Jinn\Database\Models\ColumnDiff;
use Jinn\Database\DatabaseComparer;
use Jinn\Database\Models\IndexDiff;
use Jinn\Definition\Models\Entity;
use Jinn\Definition\Models\Field;
use Jinn\Definition\Models\Index;
use Jinn\Definition\Models\Relation;
use Jinn\Generator\PhpFileWriter;
use LogicException;
use Nette\PhpGenerator\Dumper;
use Nette\PhpGenerator\PhpFile;

class MigrationCreator extends BaseMigrationCreator
{
    private NameConverter $nameConverter;
    private DatabaseComparer $databaseComparer;
    private Dumper $dumper;

    /**
     * JinnMigrationCreator constructor.
     * @throws DBALException
     */
    public function __construct()
    {
        parent::__construct(File::getFacadeRoot(), '');

        $this->nameConverter = new NameConverter();

        $connectionName = config('database.default');
        $connectionParams = config('database.connections.' . $connectionName);
        $connectionParams = $this->laravelToDoctrineParams($connectionParams);

        $this->databaseComparer = new DatabaseComparer($connectionParams, $this->nameConverter);
        $this->dumper = new Dumper();
    }

    private function laravelToDoctrineParams($connectionParams) {
        if (!empty($connectionParams['driver'])) $connectionParams['driver'] = 'pdo_' . $connectionParams['driver'];
        if (!empty($connectionParams['url'])) $connectionParams['url'] = 'pdo_' . $connectionParams['url'];
        if (!empty($connectionParams['username'])) $connectionParams['user'] = $connectionParams['username'];
        if (!empty($connectionParams['database'])) $connectionParams['dbname'] = $connectionParams['database'];

        return $connectionParams;
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
        PhpFileWriter::writePhpFile($filename, $migrationFile);

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
            $columnName = $this->nameConverter->toColumnName($column->name);
            $length = $column->length;
            $required = $column->required;
            $default = $column->default;
        } elseif ($column instanceof Column) {
            $typeName = $column->getType()->getName();
            $columnName = $column->getName();
            $length = $column->getLength();
            $required = $column->getNotnull();
            $default = $column->getDefault();
        } else {
            throw new InvalidArgumentException("Parameter must be either Field or Column");
        }

        $type = Types::toEloquentType($typeName);

        if (!$type) throw new LogicException("Unsupported field type $typeName");
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
            $columns = array_map([$this->nameConverter, 'toColumnName'], $index->columns);
        } elseif ($index instanceof DbIndex) {
            $isUnique = $index->isUnique();
            $name = $index->getName();
            $columns = $index->getColumns();
        } else {
            throw new InvalidArgumentException("Parameter must be either Jinn Index or DBAL Index");
        }
        return ["\t\$table->" . ($isUnique ? 'unique' : 'index') .
            "(['" . implode("', '", $columns). "'], '$name');\n",
            "\t\$table->drop" . ($isUnique ? 'Unique' : 'Index') . "('$name');\n"];
    }

    /**
     * @param Relation|ForeignKeyConstraint $relation
     * @return array
     */
    private function foreignKeyCode(Entity $entity, $relation): array
    {
        if ($relation instanceof Relation) {
            $name = $entity->name . $relation->name;
            $field = $this->nameConverter->toColumnName($relation->field());
            $tableName = $this->nameConverter->tableName($relation->entity);
        } elseif ($relation instanceof ForeignKeyConstraint) {
            $columns = $relation->getLocalColumns();
            if (count($columns) > 1) return ['', '']; //Eloquent can't handle multi-column migrations so we are not doing anything with it

            $name = $relation->getName();
            $field = $columns[0];
            $tableName = $relation->getForeignTableName();
        } else {
            throw new InvalidArgumentException("Parameter must be either Relation or ForeignKeyConstraint");
        }

        return ["\t\$table->foreign('$field', '$name')->references('id')->on('$tableName');\n",
            "\t\$table->dropForeign('$name');\n"];
    }

    /**
     * @param Entity $entity
     * @param bool $newTable
     * @param string $upCode
     * @param string $downCode
     * @return bool
     * @throws DBALException
     */
    protected function createColumnsMigration(Entity $entity, bool $newTable, string &$upCode, string &$downCode): bool
    {
        if (!$newTable) {
            $changes = $this->databaseComparer->compareTableColumns($entity);

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
                        $columnName = $this->nameConverter->toColumnName($diff->field->name);
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
     * @throws DBALException
     */
    protected function createIndexesMigration(Entity $entity, bool $newTable, string &$upCode, string &$downCode): bool
    {
        $changes = $this->databaseComparer->compareTableIndexes($entity);
        if (!$changes) return false;

        foreach ($changes as $diff) {
            $create1 = null;
            if ($diff->operation != IndexDiff::OP_ADD) {
                list($create1, $drop1) = $this->indexCode($diff->dbIndex);
                $upCode .= $drop1;
            }
            if ($diff->operation != IndexDiff::OP_REMOVE) {
                list($create2, $drop2) = $this->indexCode($diff->index);
                $upCode .= $create2;
                if (!$newTable) $downCode .= $drop2;
            }
            if (!$newTable && !empty($create1)) {
                $downCode .= $create1;
            }
        }
        return true;
    }

    /**
     * @param Entity $entity
     * @param string $path
     * @return string|null
     * @throws DBALException
     */
    public function createStructureMigration(Entity $entity, string $path): ?string
    {
        $tableName = $this->nameConverter->tableName($entity);
        $newTable = !$this->databaseComparer->tableExists($tableName);

        $migrationName = ($newTable ? 'create' : 'update') . '_' . $tableName . '_table_' . time();
        $this->ensureMigrationDoesntAlreadyExist($migrationName, $path);

        $upCode = "Schema::" . ($newTable ? 'create' : 'table') . "('$tableName', function (Blueprint \$table) {\n";
        $downCode = '';
        if (!$newTable) {
            $downCode = $upCode;
        }

        $changed = $this->createColumnsMigration($entity, $newTable, $upCode, $downCode);

        $changed = $this->createIndexesMigration($entity, $newTable, $upCode, $downCode) || $changed;

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

    /**
     * @param Entity $entity
     * @param string $path
     * @throws DBALException
     */
    public function createForeignKeysMigration(Entity $entity, string $path): ?string
    {
        $tableName = $this->nameConverter->tableName($entity);
        $migrationName = 'update_' . $tableName . '_foreign_keys_' . time();
        $this->ensureMigrationDoesntAlreadyExist($migrationName, $path);

        $changes = $this->databaseComparer->compareTableRelations($entity);
        if (!$changes) return null;

        $upCode = "Schema::table('$tableName', function (Blueprint \$table) {\n";
        $downCode = $upCode;

        foreach ($changes as $diff) {
            $create1 = null;
            if ($diff->operation != IndexDiff::OP_ADD) {
                list($create1, $drop1) = $this->foreignKeyCode($entity, $diff->foreignKey);
                $upCode .= $drop1;
                if ($diff->index) {
                    list(, $indDrop) = $this->indexCode($diff->index);
                    $upCode .= $indDrop;
                }
            }
            if ($diff->operation != IndexDiff::OP_REMOVE) {
                list($create2, $drop2) = $this->foreignKeyCode($entity, $diff->relation);
                $upCode .= $create2;
                $downCode .= $drop2;
            }
            if (!empty($create1)) {
                $downCode .= $create1;
            }
        }

        $upCode .= '});';
        $downCode .= '});';

        return $this->writeMigrationFile($migrationName, $upCode, $downCode, $path);
    }

    protected function getDatePrefix()
    {
        return parent::getDatePrefix() . gettimeofday()['usec'];
    }
}
