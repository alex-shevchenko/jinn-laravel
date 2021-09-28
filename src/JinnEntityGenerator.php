<?php


namespace Jinn\Laravel;


use Illuminate\Support\Str;
use Jinn\AbstractEntityGenerator;
use Jinn\Models\Entity;
use Jinn\Models\Relation;
use Nette\PhpGenerator\PhpFile;

class JinnEntityGenerator extends AbstractEntityGenerator
{
    private string $baseFolder;
    private string $appFolder;
    private string $appNamespace;
    private string $modelsNamespace;
    private string $generatedNamespace;
    private string $generatedFolder;
    private string $databasePath;

    public function __construct()
    {
        $this->modelsNamespace = config('jinn.models_namespace');
        $this->generatedFolder = config('jinn.generated_folder');
        $this->generatedNamespace = config('jinn.generated_namespace');
    }

    public function setBase($baseFolder, $appFolder, $appNamespace, $databasePath) {
        if (!$baseFolder || !$appFolder || !$appNamespace || !$databasePath) throw new \InvalidArgumentException('Folders and namespace are required');

        $this->baseFolder = $baseFolder;
        $this->appFolder = $appFolder;
        $this->appNamespace = $appNamespace;
        $this->databasePath = $databasePath;
    }

    private function name(...$parts) {
        return implode('\\', $parts);
    }

    private function nameToPath($baseFolder, $baseNamespace, $name) {
        $name = str_replace($baseNamespace, $baseFolder, $name);
        $filename = str_replace('\\', '/', $name) . '.php';
        return $filename;
    }

    protected function generateModel(Entity $entity): void
    {
        if (!$this->baseFolder) throw new \LogicException('Base folder not defined');

        $modelNamespace = $this->name($this->appNamespace, $this->modelsNamespace);
        $genNamespace = $this->name($this->generatedNamespace, $this->modelsNamespace);
        $genName = 'Base' . $entity->name;
        $genFullName = $this->name($genNamespace, $genName);

        $genFile = new PhpFile();
        $genFile->addComment("Generated by Jinn. Do not edit.");
        $genNamespace = $genFile->addNamespace($genNamespace);
        $genClass = $genNamespace->addClass($genName);
        $genClass->setExtends('Illuminate\Database\Eloquent\Model');
        $genClass->setAbstract(true);

        $defaults = [];
        foreach ($entity->fields() as $field) {
            if ($field->noModel) continue;

            $phpType = Types::toPhp($field->type);
            $genClass->addComment("@property " . ($phpType ? $phpType . ($field->required ? '' : '|null') : '') . " \$$field->name");

            if ($field->default)
                $defaults[$field->name] = $field->default;
        }
        if ($defaults) {
            $defaultsProperty = $genClass->addProperty('attributes', $defaults);
            $defaultsProperty->setProtected();
        }

        foreach ($entity->relations() as $relation) {
            $method = $genClass->addMethod($relation->name);
            $method->setPublic();

            $code = 'return $this->';
            $field = $relation->field ? Str::snake($relation->field) : null;
            switch ($relation->type) {
                case Relation::ONE_TO_MANY:
                    $code .= "hasMany(";
                    break;
                case Relation::MANY_TO_ONE:
                    $code .= "belongsTo(";
                    break;
                case Relation::MANY_TO_MANY:
                    $code .= "belongsToMany(";
                    break;
            }
            $code .= "\\$modelNamespace\\{$relation->entityName}::class";
            if ($field) $code .= ", '$field'";
            $code .= ');';
            $method->setBody($code);
        }
        JinnFileWriter::writePhpFile($this->nameToPath($this->generatedFolder, $this->generatedNamespace, $genFullName), $genFile);

        $modelFilename = $this->nameToPath($this->appFolder, $this->appNamespace, $this->name($modelNamespace, $entity->name));
        if (!file_exists($modelFilename)) {
            $modelFile = new PhpFile();
            $modelNamespace = $modelFile->addNamespace($modelNamespace);
            $modelNamespace->addUse($genFullName);
            $modelClass = $modelNamespace->addClass($entity->name);
            $modelClass->setExtends($genFullName);

            JinnFileWriter::writePhpFile($modelFilename, $modelFile);
        }
    }

    protected function generateMigrations(array $entities): void
    {
        $migrationsPath = $this->databasePath . '/migrations/';
        $creator = new JinnMigrationCreator();

        foreach ($entities as $entity) {
            $creator->createTableMigration($entity, $migrationsPath);
        }
        foreach ($entities as $entity) {
            $creator->createForeignKeysMigration($entity, $migrationsPath);
        }
    }
}
