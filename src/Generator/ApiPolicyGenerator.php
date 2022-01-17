<?php

namespace Jinn\Laravel\Generator;

use Jinn\Generator\ClassGenerator;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Dumper;

class ApiPolicyGenerator extends ClassGenerator
{
    protected function className($entity, $policies = null): string
    {
        return $entity->name . 'Policy';
    }

    protected function generateBaseClass(ClassType $genClass, $classFullName, $entity, $policies = null)
    {
        $dumper = new Dumper();

        foreach ($policies as $policy) {
            $method = $genClass->addMethod($policy->name);
            $method->addParameter('user');
            $method->addParameter('entity');

            $body = '';
            if ($policy->owner) {
                $body .= "if (\$entity->{$policy->owner} == \$user) return true;\n";
            }
            if ($policy->roles) {
                $body .= "if (in_array(\$user->role, " . $dumper->dump($policy->roles) . ")) return true;\n";
            }
            $body .= "return false;\n";
            $method->setBody($body);
        }
    }
}
