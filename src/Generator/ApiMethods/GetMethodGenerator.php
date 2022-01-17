<?php

namespace Jinn\Laravel\Generator\ApiMethods;


use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;

class GetMethodGenerator extends ApiMethodGenerator
{
    use HasEntity, HasResource;


    protected function addLogic(ClassType $genClass, Method $method): void
    {
        $resourceClass = $this->getResourceClass();
        $method->setBody("return new \\$resourceClass(\${$this->entityParamName()});\n");
    }

    public function routeMethod(): string
    {
        return 'get';
    }
}
