<?php

namespace Jinn\Laravel\Generator\ApiMethods;

use Nette\PhpGenerator\Method;

trait HasEntity
{
    use ApiMethodGeneratorTrait;

    protected function entityRoute(): string
    {
        return '/{' . lcfirst($this->entityName()) . '}';
    }

    protected function addParameters(Method $method): void
    {
        $param = $method->addParameter($this->entityParamName());
        $param->setType($this->modelClass());
    }

    public function policyParam(): string
    {
        return '$' . $this->entityParamName();
    }
}
