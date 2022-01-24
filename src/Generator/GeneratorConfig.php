<?php


namespace Jinn\Laravel\Generator;

use Jinn\Generator\GeneratorConfig as BaseGeneratorConfig;

class GeneratorConfig extends BaseGeneratorConfig
{
    public $baseControllerClass;
    public $authMiddleware;

    public $apiPolicyNamespace;
    public $apiRequestNamespace;

    public function __construct()
    {
        $this->appNamespace = substr(app()->getNamespace(), 0, -1);
        $this->appFolder = app()['path'];
        $this->generatedNamespace = config('jinn.generated_namespace');
        $this->generatedFolder = config('jinn.generated_folder');

        $this->modelNamespace = config('jinn.models_namespace');
        $this->viewNamespace = config('jinn.views_namespace');
        $this->apiControllerNamespace = config('jinn.api_controllers_namespace');
        $this->apiPolicyNamespace = config('jinn.policies_namespace');
        $this->apiRequestNamespace = config('jinn.api_requests_namespace');

        $this->baseControllerClass = config('jinn.base_controller_class');

        $this->authMiddleware = config('jinn.auth_middleware');

        $this->migrationsPath = app()->databasePath() . '/migrations/';
    }

    public function output($string)
    {
        if ($this->output)
            call_user_func($this->output, $string);
    }
}
