<?php

namespace Jinn\Laravel\Generator\ApiMethods;


use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;

class ListMethodGenerator extends ApiMethodGenerator
{
    use HasResource, HasUtilityMethod;

    protected function addQueryMethod(ClassType $genClass, $queryMethodName): void
    {
        $this->addUtilityMethod($genClass, $queryMethodName, [], "return \\{$this->modelClass()}::query();\n");
    }

    protected function queryMethodParams(): string
    {
        return '';
    }

    protected function addLogic(ClassType $genClass, Method $method): void
    {
        $queryMethodName = 'get' . ucfirst($this->method()->name) . 'Query';
        $this->addQueryMethod($genClass, $queryMethodName);

        $resourceClass = $this->getResourceClass();

        $method->addBody("return \\$resourceClass::collection(\$this->{$queryMethodName}({$this->queryMethodParams()})->get());\n");
    }

    function routeMethod(): string
    {
        return 'get';
    }
}
