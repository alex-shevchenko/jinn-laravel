<?php

namespace Jinn\Laravel\Generator;

use Jinn\Definition\Models\ApiController;
use Jinn\Generator\ClassGenerator;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Dumper;
use Symfony\Component\Mime\Exception\LogicException;

class ApiPolicyGenerator extends ClassGenerator
{
    protected function className($controller, $param = null): string
    {
        /** @var $controller ApiController */
        return $controller->entity->name . 'Policy';
    }

    protected function generateBaseClass(ClassType $genClass, $classFullName, $controller, $param = null)
    {
        /** @var $controller ApiController */
        $dumper = new Dumper();

        foreach ($controller->methods() as $apiMethod) {
            if ($policy = $apiMethod->policy) {
                $method = $genClass->addMethod($policy->name);
                $method->addParameter('user')->setType($policy->anonymous ? '?object' : null);
                $method->addParameter('entity')->setDefaultValue(null);

                $body = '';
                if ($policy->owner) {
                    $body .= "if (\$user->is(\$entity->{$policy->owner})) return true;\n";
                }
                if ($policy->roles) {
                    $body .= "if (in_array(\$user->role, " . $dumper->dump($policy->roles) . ")) return true;\n";
                }
                $body .= "return false;";

                $method->setBody($body);
            }
        }
    }
}
