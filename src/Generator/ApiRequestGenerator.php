<?php

namespace Jinn\Laravel\Generator;

use Illuminate\Support\Str;
use Jinn\Definition\Models\ApiMethod;
use Jinn\Definition\Models\Entity;
use Jinn\Generator\ClassGenerator;
use Jinn\Laravel\Utils\Types;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Dumper;

class ApiRequestGenerator extends ClassGenerator
{
    protected function className($apiMethod, $entity = null): string
    {
        /** @var $entity Entity */
        return $entity->name . Str::ucfirst($apiMethod->name) . 'Request';
    }

    protected function generateBaseClass(ClassType $genClass, $classFullName, $apiMethod, $entity = null)
    {
        /** @var $entity Entity */
        $dumper = new Dumper();

        $genClass->setExtends('Illuminate\Foundation\Http\FormRequest');

        $rules = $genClass->addMethod('rules');

        $body = "return [\n";

        foreach ($apiMethod->view->fields as $name) {
            $validations = [];

            if ($apiMethod->type == ApiMethod::UPDATE) {
                $validations[] = 'sometimes';
            }

            $field = $entity->field($name);

            if ($field->required) {
                $validations[] = 'required';
            }
            $typeValidation = Types::toValidation($field->type);
            if ($typeValidation) $validations[] = $typeValidation;

            if ($defaultLength = Types::defaultLength($field->type)) {
                $length = $field->length;
                if (!$length) $length = $defaultLength;
                $validations[] = 'max:' . $length;
            }

            if ($entity->hasIndex($name)) {
                $index = $entity->index($name);
                if ($index->isUnique && count($index->columns) == 1 && $index->columns[0] == $name) {
                    $validations[] = 'unique:' . $this->modelClass($entity->name);
                }
            }

            $body .= "\t'{$name}' => " . $dumper->dump($validations) . ",\n";
        }

        $body .= "];";
        $rules->setBody($body);
    }
}
