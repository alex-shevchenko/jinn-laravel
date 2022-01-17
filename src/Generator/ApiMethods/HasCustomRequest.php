<?php

namespace Jinn\Laravel\Generator\ApiMethods;

use Jinn\Laravel\Generator\ApiControllerGenerator;

trait HasCustomRequest
{
    use ApiMethodGeneratorTrait;

    protected function getRequestClass(): string
    {
        $generator = $this->factory()->get(ApiControllerGenerator::API_REQUEST);
        return $generator->generate($this->method(), $this->entity());
    }
}
