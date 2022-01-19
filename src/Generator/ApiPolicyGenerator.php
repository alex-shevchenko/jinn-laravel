<?php

namespace Jinn\Laravel\Generator;

use Jinn\Generator\ClassGenerator;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Dumper;
use Symfony\Component\Mime\Exception\LogicException;

class ApiPolicyGenerator extends ClassGenerator
{
    protected function className($entity, $policies = null): string
    {
        return $entity->name . 'Policy';
    }

    protected function generateBaseClass(ClassType $genClass, $classFullName, $entity, $policies = null)
    {
        $dumper = new Dumper();

        foreach ($policies as $policy => $param) {
            $method = $genClass->addMethod($policy->name);
            $method->addParameter('user');
            if ($param)
                $method->addParameter($param);

            $body = '';
            if ($policy->owner) {
                if (!$param) throw new LogicException("Method $policy cannot have owner policy as it has no entity");
                $body .= "if (\${$param}->{$policy->owner} == \$user) return true;\n";
            }
            if ($policy->roles) {
                $body .= "if (in_array(\$user->role, " . $dumper->dump($policy->roles) . ")) return true;\n";
            }
            $body .= "return false;\n";
            $method->setBody($body);
        }
    }
}
