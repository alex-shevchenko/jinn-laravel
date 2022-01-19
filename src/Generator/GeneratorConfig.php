<?php


namespace Jinn\Laravel\Generator;

use Jinn\Generator\GeneratorConfig as BaseGeneratorConfig;

class GeneratorConfig extends BaseGeneratorConfig
{
    public $baseControllerClass;
    public $authMiddleware;

    public $apiPolicyNamespace;
    public $apiRequestNamespace;

    /**
     * @var callable
     */
    public $output;

    public function __construct()
    {
        $this->appNamespace = substr(app()->getNamespace(), 0, -1);
        $this->appFolder = app()['path'];
        $this->generatedNamespace = config('jinn.generated_namespace', 'JinnGenerated');
        $this->generatedFolder = config('jinn.generated_folder', 'jinn/gen');

        $this->modelNamespace = config('jinn.models_namespace', 'Models');
        $this->viewNamespace = config('jinn.views_namespace', 'Http\Resources\Api');
        $this->apiControllerNamespace = config('jinn.api_controllers_namespace', 'Http\Controllers\Api');
        $this->apiPolicyNamespace = config('jinn.policies_namespace', 'Policies');
        $this->apiRequestNamespace = config('jinn.api_requests_namespace', 'Http\Requests\Api');

        $this->baseControllerClass = config('jinn.base_controller_class', 'App\Http\Controllers\Controller');

        $this->authMiddleware = config('jinn.auth_middleware', 'auth');

        $this->migrationsPath = app()->databasePath() . '/migrations/';
    }

    public function output($string)
    {
        if ($this->output)
            call_user_func($this->output, $string);
    }
}
