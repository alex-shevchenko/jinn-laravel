<?php


namespace Jinn\Laravel\Generator;


use Illuminate\Support\Str;
use Jinn\Definition\Models\Entity;
use Jinn\Generator\ClassGenerator;
use Nette\PhpGenerator\ClassType;

class ViewGenerator extends ClassGenerator
{
    private array $views;

    public function generate($view, $entity = null)
    {
        if (!isset($this->views[$view->fullName])) {
            $className = parent::generate($view, $entity);
            $this->views[$view->fullName] = $className;
        }

        return $this->views[$view->fullName];
    }

    protected function className($view, $entity = null): string
    {
        /** @var Entity $entity */
        return $entity->name . ($view->name == 'default' ? '' : Str::ucfirst($view->name)) . 'Resource';
    }

    protected function generateBaseClass(ClassType $genClass, $classFullName, $view, $entity = null)
    {
        $genClass->setExtends('Illuminate\Http\Resources\Json\JsonResource');

        $genMethod = $genClass->addMethod('toArray');

        $genMethod->addParameter('request');

        $body = "return [\n";

        foreach ($view->fields as $name) {
            $body .= "\t'$name' => \$this->$name,\n";
        }

        $body .= "];";
        $genMethod->setBody($body);
    }
}
