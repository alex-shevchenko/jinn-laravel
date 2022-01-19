<?php

namespace Jinn\Laravel\Generator\ApiMethods;


use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;

class RelatedListMethodGenerator extends ListMethodGenerator
{
    use HasEntity;

    protected function addQueryMethod(ClassType $genClass, $queryMethodName): void
    {
        $this->addUtilityMethod($genClass, $queryMethodName, [$this->entityParamName() => $this->modelClass()], "\${$this->entityParamName()}->{$this->method()->relation}();\n");
    }

    protected function queryMethodParams(): string
    {
        return '$' . $this->entityParamName();
    }

    function routeMethod(): string
    {
        return 'get';
    }
}
