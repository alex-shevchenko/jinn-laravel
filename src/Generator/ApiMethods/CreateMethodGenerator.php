<?php

namespace Jinn\Laravel\Generator\ApiMethods;


use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;

class CreateMethodGenerator extends ApiMethodGenerator
{
    use HasCustomRequest, HasResource, HasFillMethod;

    protected function addLogic(ClassType $genClass, Method $method): void
    {
        $this->addFillMethod($genClass);

        $resourceClass = $this->getResourceClass();

        $method->addBody(
                    "\${$this->entityParamName()} = new \\{$this->modelClass()}();\n" .
                    "\$this->fill(\${$this->entityParamName()}, \$request->validated());\n" .
                    "\${$this->entityParamName()}->save();\n" .
                    "return new \\$resourceClass(\${$this->entityParamName()});"
        );
    }

    public function routeMethod(): string
    {
        return 'post';
    }
}
