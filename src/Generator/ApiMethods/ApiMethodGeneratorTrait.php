<?php

namespace Jinn\Laravel\Generator\ApiMethods;


use Jinn\Definition\Models\ApiMethod;
use Jinn\Definition\Models\Entity;
use Jinn\Generator\GeneratorFactory;
use Nette\PhpGenerator\ClassType;

trait ApiMethodGeneratorTrait
{
    abstract protected function factory(): GeneratorFactory;
    abstract protected function method(): ApiMethod;
    abstract protected function entity(): Entity;
    abstract protected function entityName(): string;
    abstract protected function modelClass(): string;
    abstract protected function entityParamName(): string;
}
