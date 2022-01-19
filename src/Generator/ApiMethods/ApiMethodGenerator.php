<?php

namespace Jinn\Laravel\Generator\ApiMethods;


use Illuminate\Support\Str;
use Jinn\Definition\Models\ApiController;
use Jinn\Definition\Models\ApiMethod;
use Jinn\Definition\Models\Entity;
use Jinn\Generator\GeneratorFactory;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;

abstract class ApiMethodGenerator
{
    private ApiMethod $method;
    private ApiController $controller;
    private string $modelClass;
    private GeneratorFactory $factory;

    public function __construct(ApiMethod $method, ApiController $controller, string $modelClass, GeneratorFactory $factory)
    {
        $this->method = $method;
        $this->controller = $controller;
        $this->modelClass = $modelClass;
        $this->factory = $factory;
    }

    protected function factory(): GeneratorFactory
    {
        return $this->factory;
    }
    protected function method(): ApiMethod
    {
        return $this->method;
    }
    protected function entityName(): string
    {
        return $this->controller->name();
    }
    protected function entityParamName(): string
    {
        return lcfirst($this->entityName());
    }
    protected function entity(): Entity
    {
        return $this->controller->entity;
    }
    protected function modelClass(): string
    {
        return $this->modelClass;
    }

    protected function entityRoute(): string
    {
        return '';
    }

    protected function getRequestClass(): string
    {
        return 'Illuminate\Http\Request';
    }

    protected function addParameters(Method $method): void
    {
    }

    public function hasEntity(): bool
    {
        return false;
    }

    private function addPolicy(Method $method): void
    {
        if ($this->method()->policy)
            $method->addBody("\$this->authorize('{$this->method->name}', " . ($this->hasEntity() ? '$' . $this->entityParamName() : "\\{$this->modelClass()}::class") . ");\n");
    }

    abstract protected function addLogic(ClassType $genClass, Method $method): void;

    private function addApiMethod(ClassType $genClass): void
    {
        $method = $genClass->addMethod($this->method()->name);
        $param = $method->addParameter('request');
        $param->setType($this->getRequestClass());

        $this->addParameters($method);
        $this->addPolicy($method);
        $this->addLogic($genClass, $method);
    }

    public function route(): string
    {
        if ($this->method->route) return $this->method->route;

        $route = Str::plural(Str::snake($this->entityName()));
        $route .= $this->entityRoute();
        $route .= ($this->method->type != $this->method->name) ? '/' . $this->method->name : '';

        return $route;
    }

    abstract public function routeMethod(): string;

    public function generate(ClassType $genClass): void
    {
        $this->addApiMethod($genClass);
    }

    public static function get(ApiMethod $method, ApiController $controller, string $modelClass, GeneratorFactory $factory): ApiMethodGenerator
    {
        $className = '\\' . __NAMESPACE__ . '\\' . ucfirst($method->type) . 'MethodGenerator';
        return new $className($method, $controller, $modelClass, $factory);
    }
}
