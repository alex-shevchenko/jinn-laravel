<?php

namespace Jinn\Laravel\Generator\ApiMethods;


use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;

class DeleteMethodGenerator extends ApiMethodGenerator
{
    use HasEntity;

    protected function addLogic(ClassType $genClass, Method $method): void
    {
        $method->setBody("\${$this->entityParamName()}->delete();\n");
    }

    public function routeMethod(): string
    {
        return 'delete';
    }
}
