<?php


namespace Jinn\Laravel\Generator;


use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Jinn\Definition\Models\Entity;
use Jinn\Definition\Models\Relation;
use Jinn\Generator\ClassGenerator;
use Jinn\Laravel\Utils\Types;
use Nette\PhpGenerator\ClassType;

class ModelGenerator extends ClassGenerator
{
    protected function className($entity, $param = null): string
    {
        return $entity->name;
    }

    private function handleFields(ClassType $genClass, array $fields)
    {
        $defaults = [];
        $casts = [];
        foreach ($fields as $field) {
            if ($field->noModel) continue;

            $phpType = Types::toPhp($field->type);
            $genClass->addComment("@property " . ($phpType ? $phpType . ($field->required ? '' : '|null') : '') . " \$$field->name");

            $cast = Types::toEloquentCast($field->type);
            if ($cast)
                $casts[$field->name] = $cast;

            if ($field->default)
                $defaults[$field->name] = $field->default;
        }
        if ($defaults) {
            $defaultsProperty = $genClass->addProperty('attributes', $defaults);
            $defaultsProperty->setProtected();
        }
        if ($casts) {
            $castsProperty = $genClass->addProperty('casts', $casts);
            $castsProperty->setProtected();
        }
    }

    private function handleRelations(ClassType $genClass, $relations)
    {
        foreach ($relations as $relation) {
            if ($relation->noModel) continue;

            $method = $genClass->addMethod($relation->name);
            $method->setPublic();

            $code = 'return $this->';
            $comment = "@return \\";
            $field = $relation->field ? Str::snake($relation->field) : null;
            switch ($relation->type) {
                case Relation::ONE_TO_MANY:
                    $code .= "hasMany(";
                    $comment .= HasMany::class;
                    break;
                case Relation::MANY_TO_ONE:
                    $code .= "belongsTo(";
                    $comment .= BelongsTo::class;
                    break;
                case Relation::MANY_TO_MANY:
                    $code .= "belongsToMany(";
                    $comment .= BelongsToMany::class;
                    break;
            }
            $relationClass = $this->modelClass($relation->entityName);
            $code .= "\\" . $relationClass . '::class';
            if ($field) $code .= ", '$field'";
            $code .= ');';
            $method->setBody($code);
            $method->addComment($comment);

            $genClass->addComment("@property \\{$relationClass}" . ($relation->type != Relation::MANY_TO_ONE ? '[]' : '') . " {$relation->name}");
        }
    }

    protected function generateBaseClass(ClassType $genClass, $classFullName, $entity, $param = null)
    {
        /** @var Entity $entity */
        $extends = ($entity->extends ?? 'Illuminate\Database\Eloquent\Model');

        $genClass->setExtends($extends);
        foreach ($entity->implements as $implement) {
            $genClass->addImplement($implement);
        }
        foreach ($entity->traits as $trait) {
            $genClass->addTrait($trait);
        }

        $guardedProperty = $genClass->addProperty('guarded', []);
        $guardedProperty->setProtected();

        $primaryKeyProperty = $genClass->addProperty('primaryKey', 'id');
        $primaryKeyProperty->setProtected();

        $this->handleFields($genClass, $entity->fields());
        $this->handleRelations($genClass, $entity->relations());
    }
}
