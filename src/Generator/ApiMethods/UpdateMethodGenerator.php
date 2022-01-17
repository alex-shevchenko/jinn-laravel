<?php

namespace Jinn\Laravel\Generator\ApiMethods;


use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;

class UpdateMethodGenerator extends ApiMethodGenerator
{
    use HasCustomRequest, HasEntity, HasFillMethod, HasResource;

    protected function addLogic(ClassType $genClass, Method $method): void
    {
        $this->addFillMethod($genClass);

        $resourceClass = $this->getResourceClass();

        $method->addBody("\$this->fill(\${$this->entityParamName()}, \$request->validated());\n" .
                    "\${$this->entityParamName()}->save();\nreturn new \\$resourceClass(\${$this->entityParamName()});");
    }

    public function routeMethod(): string
    {
        return 'put';
    }
}
