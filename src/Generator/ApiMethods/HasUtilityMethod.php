<?php

namespace Jinn\Laravel\Generator\ApiMethods;


use Nette\PhpGenerator\ClassType;

trait HasUtilityMethod
{
    protected function addUtilityMethod(ClassType $genClass, string $name, array $params, string $body): void
    {
        if ($genClass->hasMethod($name)) return;

        $method = $genClass->addMethod($name);
        foreach ($params as $name => $type) {
            $param = $method->addParameter($name);
            $param->setType($type);
        }
        $method->setProtected();
        $method->setBody($body);
    }
}
