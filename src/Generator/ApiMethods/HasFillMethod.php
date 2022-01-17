<?php

namespace Jinn\Laravel\Generator\ApiMethods;

use Nette\PhpGenerator\ClassType;

trait HasFillMethod
{
    use ApiMethodGeneratorTrait, HasUtilityMethod;

    private function addFillMethod(ClassType $genClass)
    {
        $this->addUtilityMethod($genClass, 'fill',
            [$this->entityParamName() => $this->modelClass(), 'data' => 'array'],
            "\${$this->entityParamName()}->fill(\$data);");
    }
}
