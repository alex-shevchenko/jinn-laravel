<?php


namespace Jinn\Laravel\Generator;


use Illuminate\Support\Str;
use Jinn\Database\NameConverterInterface;
use Jinn\Definition\Models\Entity;

class NameConverter implements NameConverterInterface
{
    public function tableName(Entity $entity): string
    {
        $tableName = $entity->name;
        if (!$entity->isPivot)
            $tableName = Str::pluralStudly($tableName);
        return Str::snake($tableName);
    }

    public function toColumnName(string $fieldName): string
    {
        return Str::snake($fieldName);
    }

    public function toFieldName(string $columnName): string
    {
        return Str::snake($columnName);

    }
}
