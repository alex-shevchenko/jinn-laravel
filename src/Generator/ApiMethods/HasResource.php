<?php

namespace Jinn\Laravel\Generator\ApiMethods;


use Jinn\Generator\GeneratorFactory;

trait HasResource
{
    use ApiMethodGeneratorTrait;

    protected function getResourceClass()
    {
        $view = $this->method()->view;
        $generator = $this->factory()->get(GeneratorFactory::VIEW);
        return $generator->generate($view, $this->entity());
    }
}
