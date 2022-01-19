<?php

namespace Jinn\Laravel\Generator;

use Jinn\Definition\Models\ApiController;
use Jinn\Definition\Models\Entity;
use Jinn\Generator\ClassGenerator;
use Jinn\Laravel\Generator\ApiMethods\ApiMethodGenerator;
use Nette\PhpGenerator\ClassType;

class ApiControllerGenerator extends ClassGenerator
{
    const API_REQUEST = 'apiRequest';
    const API_POLICY = 'apiPolicy';

    protected function className($apiController, $param = null): string
    {
        /** @var ApiController $apiController */
        return $apiController->name() . 'Controller';
    }

    protected function generatePolicy(Entity $entity, array $policies): void
    {
        $generator = $this->factory->get(self::API_POLICY);
        $generator->generate($entity, $policies);
    }

    protected function generateBaseClass(ClassType $genClass, $classFullName, $apiController, $param = null)
    {
        /** @var ApiController $apiController */
        $routes = '';
        $policies = [];

        $entityName = $apiController->name();
        $modelClass = $this->modelClass($entityName);


        $genClass->setExtends($this->config->baseControllerClass);

        foreach ($apiController->methods() as $apiMethod) {
            $methodGenerator = ApiMethodGenerator::get($apiMethod, $apiController, $modelClass, $this->factory);
            $methodGenerator->generate($genClass);

            if ($apiMethod->policy) {
                $apiMethod->policy->hasEntity = $methodGenerator->hasEntity();
                $policies[] = $apiMethod->policy;
            }

            if ($apiMethod->route !== false) {
                $routes .= "\tRoute::{$methodGenerator->routeMethod()}('{$methodGenerator->route()}', [\\$classFullName::class, '{$apiMethod->name}'])";
                if ($apiMethod->authRequired)
                    $routes .= "->middleware('{$this->config->authMiddleware}')";
                $routes .= ";\n";
            }
        }

        if ($policies)
        $this->generatePolicy($apiController->entity, $policies);

        return $routes;
    }
}
