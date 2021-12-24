<?php


namespace Jinn\Laravel\Generator;


use Doctrine\DBAL\Exception as DBALException;
use Jinn\Definition\Models\ApiController;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Str;
use Jinn\Definition\Models\ApiMethod;
use Jinn\Definition\Models\Application;
use Jinn\Definition\Models\Policy;
use Jinn\Definition\Models\View;
use Jinn\Generator\AbstractModelGenerator;
use Jinn\Definition\Models\Entity;
use Jinn\Definition\Models\Relation;
use Jinn\Generator\ClassGenerator;
use Jinn\Generator\PhpFileWriter;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Dumper;
use InvalidArgumentException;

class ModelGenerator extends AbstractModelGenerator
{
    private string $appNamespace;
    private string $modelsNamespace;
    private string $policiesNamespace;
    private string $apiResourcesNamespace;
    private string $apiRequestsNamespace;
    private string $apiControllersNamespace;
    private string $generatedFolder;
    private string $migrationsPath;
    private string $authMiddleware;
    private OutputStyle $output;
    private Dumper $dumper;

    private ClassGenerator $classGenerator;

    private array $views;

    public function __construct(array $params, ?OutputStyle $output = null)
    {
        $this->classGenerator = new ClassGenerator($params['appNamespace'], $params['appFolder'], config('jinn.generated_namespace'), config('jinn.generated_folder'), [$this, 'writeLine']);

        $this->modelsNamespace = config('jinn.models_namespace');
        $this->policiesNamespace = config('jinn.policies_namespace');
        $this->generatedFolder = config('jinn.generated_folder');
        $this->apiResourcesNamespace = config('jinn.api_resources_namespace');
        $this->apiRequestsNamespace = config('jinn.api_requests_namespace');
        $this->apiControllersNamespace = config('jinn.api_controllers_namespace');
        $this->authMiddleware = config('jinn.auth_middleware');

        $this->appNamespace = $params['appNamespace'];
        $this->migrationsPath = $params['migrationsPath'];

        $this->output = $output;
        $this->dumper = new Dumper();
    }

    public function writeLine(string $line)
    {
        if ($this->output) $this->output->writeln($line);
    }

    private function modelClass($name) {
        return $this->classGenerator->name($this->appNamespace, $this->modelsNamespace, $name);
    }

    protected function generateModel(Entity $entity): void
    {
        $this->classGenerator->generateClass($entity->name, $this->modelsNamespace,
            function(ClassType $genClass) use($entity) {

                $extends = ($entity->extends ?? 'Illuminate\Database\Eloquent\Model');

                $genClass->setExtends($extends);
                foreach ($entity->implements as $implement) {
                    $genClass->addImplement($implement);
                }
                foreach ($entity->traits as $trait) {
                    $genClass->addTrait($trait);
                }

                $guardedProperty = $genClass->addProperty('guarded', []);
                $guardedProperty->setProtected();

                $primaryKeyProperty = $genClass->addProperty('primaryKey', 'id');
                $primaryKeyProperty->setProtected();

                $defaults = [];
                foreach ($entity->fields() as $field) {
                    if ($field->noModel) continue;

                    $phpType = Types::toPhp($field->type);
                    $genClass->addComment("@property " . ($phpType ? $phpType . ($field->required ? '' : '|null') : '') . " \$$field->name");

                    if ($field->default)
                        $defaults[$field->name] = $field->default;
                }
                if ($defaults) {
                    $defaultsProperty = $genClass->addProperty('attributes', $defaults);
                    $defaultsProperty->setProtected();
                }

                foreach ($entity->relations() as $relation) {
                    if ($relation->noModel) continue;

                    $method = $genClass->addMethod($relation->name);
                    $method->setPublic();

                    $code = 'return $this->';
                    $field = $relation->field ? Str::snake($relation->field) : null;
                    switch ($relation->type) {
                        case Relation::ONE_TO_MANY:
                            $code .= "hasMany(";
                            break;
                        case Relation::MANY_TO_ONE:
                            $code .= "belongsTo(";
                            break;
                        case Relation::MANY_TO_MANY:
                            $code .= "belongsToMany(";
                            break;
                    }
                    $code .= "\\" . $this->classGenerator->name($this->appNamespace, $this->modelsNamespace, $relation->entityName) . '::class';
                    if ($field) $code .= ", '$field'";
                    $code .= ');';
                    $method->setBody($code);
                }
            }
        );
    }

    /**
     * @param Entity $entity
     * @param Policy[] $policies
     */
    protected function generatePolicy(Entity $entity, array $policies): void
    {
        $this->classGenerator->generateClass($entity->name . 'Policy', $this->policiesNamespace,
            function(ClassType $genClass) use ($entity, $policies) {
                foreach ($policies as $policy) {
                    $method = $genClass->addMethod($policy->name);
                    $method->addParameter('user');
                    $method->addParameter('entity');

                    $body = '';
                    if ($policy->owner) {
                        $body .= "if (\$entity->{$policy->owner} == \$user) return true;\n";
                    }
                    if ($policy->roles) {
                        $body .= "if (in_array(\$user->role, " . $this->dumper->dump($policy->roles) . ")) return true;\n";
                    }
                    $body .= "return false;\n";
                    $method->setBody($body);
                }
            }
        );
    }
    protected function generateApiControllers(Application $application): void
    {
        $routes = "<?php\n/**\n * Generated by Jinn. Do not edit.\n */\n\n";
        $routes .= "use Illuminate\Support\Facades\Route;\n\n";
        $routes .= "return function() {\n";

        foreach ($application->apiControllers() as $apiController) {
            $routes .= $this->generateApiController($apiController);
        }

        $routes .= "};\n";

        PhpFileWriter::writeFile($this->generatedFolder . '/routes/api.php', $routes);
        $this->writeLine("Generated file\t<info>routes/api.php</info>");
    }

    protected function generateApiRequest(ApiController $apiController, ApiMethod $apiMethod)
    {
        $className = $apiController->name() . Str::ucfirst($apiMethod->name) . 'Request';
        $entity = $apiController->entity;

        return $this->classGenerator->generateClass($className, $this->apiRequestsNamespace,
            function(ClassType $genClass, $classFullName) use ($apiMethod, $entity) {
                $genClass->setExtends('Illuminate\Foundation\Http\FormRequest');

                $rules = $genClass->addMethod('rules');

                $body = "return [\n";

                foreach ($apiMethod->view->fields as $name) {
                    $validations = [];

                    if ($apiMethod->type == ApiMethod::UPDATE) {
                        $validations[] = 'sometimes';
                    }

                    $field = $entity->field($name);

                    if ($field->required) {
                        $validations[] = 'required';
                    }
                    $typeValidation = Types::toValidation($field->type);
                    if ($typeValidation) $validations[] = $typeValidation;

                    if ($field->type == 'string') {
                        $length = $field->length;
                        if (!$length) $length = Types::defaultLength($field->type);
                        $validations[] = 'max:' . $length;
                    }

                    if ($entity->hasIndex($name)) {
                        $index = $entity->index($name);
                        if ($index->isUnique && count($index->columns) == 1 && $index->columns[0] == $name) {
                            $validations[] = 'unique:' . $classFullName;
                        }
                    }

                    $body .= "\t'{$name}' => " . $this->dumper->dump($validations) . ",\n";
                }

                $body .= "];";
                $rules->setBody($body);
            }
        );
    }

    protected function generateView(Entity $entity, View $view): void {
        $className = $entity->name . ($view->name == 'default' ? '' : Str::ucfirst($view->name)) . 'Resource';

        $className = $this->classGenerator->generateClass($className, $this->apiResourcesNamespace,
            function(ClassType $genClass) use ($view, $entity) {
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
        );

        $this->views[$view->fullName] = $className;
    }

    protected function generateApiController(ApiController $apiController): string
    {
        $routes = '';

        $policies = [];

        $this->classGenerator->generateClass($apiController->name() . 'Controller', $this->apiControllersNamespace,
            function(ClassType $genClass, $classFullName) use ($apiController, &$routes, &$policies) {
                $modelClass = $this->modelClass($apiController->name());
                $entityParamName = Str::camel($apiController->name());

                $routesRoot = Str::plural(Str::snake($apiController->name()));

                $genClass->setExtends('App\Http\Controllers\Controller');

                $fill = $genClass->addMethod('fill');
                $param = $fill->addParameter($entityParamName);
                $param->setType($modelClass);
                $param = $fill->addParameter('data');
                $param->setType('array');
                $fill->setProtected();
                $fill->setBody("\${$entityParamName}->fill(\$data);");

                foreach ($apiController->methods() as $apiMethod) {
                    $method = $genClass->addMethod($apiMethod->name);

                    $param = $method->addParameter('request');

                    if ($apiMethod->type == ApiMethod::CREATE || $apiMethod->type == ApiMethod::UPDATE) {
                        $requestClass = $this->generateApiRequest($apiController, $apiMethod);
                        $param->setType($requestClass);
                    } else {
                        $param->setType('Illuminate\Http\Request');
                    }

                    $resourceClass = '';
                    if (in_array($apiMethod->type, [ApiMethod::LIST, ApiMethod::GET, ApiMethod::UPDATE, ApiMethod::CREATE])) {
                        if (!isset($this->views[$apiMethod->view->fullName]))
                            $this->generateView($apiController->entity, $apiMethod->view);
                        $resourceClass = $this->views[$apiMethod->view->fullName];
                    }

                    $entityRoute = '';
                    if (!in_array($apiMethod->type, [ApiMethod::LIST, ApiMethod::CREATE]) || $apiMethod->relation) {
                        $entityRoute = '/{' . $entityParamName . '}';

                        $param = $method->addParameter($entityParamName);
                        $param->setType($modelClass);
                    }

                    $body = '';
                    if ($apiMethod->policy) {
                        $policies[] = $apiMethod->policy;
                        $body .= "\$this->authorize('{$apiMethod->name}', " .
                            ($entityRoute ? "\$$entityParamName" : "\\$modelClass::class") . ");\n";
                    }

                    $route = $routesRoot;

                    switch ($apiMethod->type) {
                        case ApiMethod::LIST:
                            if ($apiMethod->relation) {
                                $queryMethodName = 'get' . Str::studly($apiMethod->relation) . 'Query';
                                $queryMethodBody = "return \${$entityParamName}->{$apiMethod->relation}();";
                                $queryMethodParameters = "\$$entityParamName";
                            } else {
                                $queryMethodName = 'getQuery';
                                $queryMethodBody = "return \\$modelClass::query();\n";
                                $queryMethodParameters = '';
                            }
                            $queryMethod = $genClass->addMethod($queryMethodName);
                            $queryMethod->setProtected();
                            $queryMethod->setBody($queryMethodBody);
                            if ($apiMethod->relation) {
                                $queryMethod->addParameter($entityParamName);
                            }

                            $body .= "return \\$resourceClass::collection(\$this->{$queryMethodName}($queryMethodParameters)->get());\n";
                            $routeMethod = 'get';
                            break;
                        case ApiMethod::GET:
                            $body .= "return new \\$resourceClass(\$$entityParamName);\n";
                            $routeMethod = 'get';
                            break;
                        case ApiMethod::CREATE:
                            $body .= "\${$entityParamName} = new \\$modelClass();\n\$this->fill(\${$entityParamName}, \$request->validated());\n";
                            $body .= "\${$entityParamName}->save();\nreturn new \\$resourceClass(\${$entityParamName});";
                            $routeMethod = 'post';
                            break;
                        case ApiMethod::UPDATE:
                            $body .= "\$this->fill(\${$entityParamName}, \$request->validated());\n";
                            $body .= "\${$entityParamName}->save();\nreturn new \\$resourceClass(\${$entityParamName});";
                            $routeMethod = 'put';
                            break;
                        case ApiMethod::DELETE:
                            $body .= "\${$entityParamName}->delete();\n";
                            $routeMethod = 'delete';
                            break;
                        default:
                            throw new InvalidArgumentException("Unknown api method type {$apiMethod->type}");
                    }
                    $route .= $entityRoute;
                    if ($apiMethod->type != $apiMethod->name) {
                        $route .= '/' . $apiMethod->name;
                    }
                    if ($apiMethod->route) $route = $apiMethod->route;

                    $method->setBody($body);

                    if ($apiMethod->route !== false) {
                        $routes .= "\tRoute::$routeMethod('$route', [\\$classFullName::class, '{$apiMethod->name}'])";
                        if ($apiMethod->authRequired)
                            $routes .= "->middleware('{$this->authMiddleware}')";
                        $routes .= ";\n";
                    }
                }
            }
        );

        if ($policies)
            $this->generatePolicy($apiController->entity, $policies);

        return $routes;
    }

    /**
     * @param array $entities
     * @throws DBALException
     */
    protected function generateMigrations(array $entities): void
    {
        $creator = new MigrationCreator();

        foreach ($entities as $entity) {
            $filename = $creator->createStructureMigration($entity, $this->migrationsPath);
            if ($filename) $this->writeLine("Generated migration <info>$filename</info>");

        }
        foreach ($entities as $entity) {
            $filename = $creator->createForeignKeysMigration($entity, $this->migrationsPath);
            if ($filename) $this->writeLine("Generated migration <info>$filename</info>");
        }
    }
}
