<?php

namespace Jinn\Laravel\Generator\ApiMethods;

use Nette\PhpGenerator\Method;

trait HasEntity
{
    use ApiMethodGeneratorTrait;

    protected function entityParamName(): string
    {
        return lcfirst($this->entityName());
    }

    protected function entityRoute(): string
    {
        return '/{' . lcfirst($this->entityName()) . '}';
    }

    protected function addParameters(Method $method): void
    {
        $param = $method->addParameter($this->entityParamName());
        $param->setType($this->modelClass());
    }

    protected function policyParam(): string
    {
        return '$' . $this->entityParamName();
    }
}
