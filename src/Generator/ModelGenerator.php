<?php


namespace Jinn\Laravel\Generator;


use App\Models\Product;
use Doctrine\DBAL\Exception as DBALException;
use Jinn\Definition\Models\ApiController;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Str;
use Jinn\Definition\Models\ApiMethod;
use Jinn\Definition\Models\Application;
use Jinn\Definition\Models\Policy;
use Jinn\Generator\AbstractModelGenerator;
use Jinn\Definition\Models\Entity;
use Jinn\Definition\Models\Relation;
use Jinn\Generator\PhpFileWriter;
use LogicException;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Dumper;
use Nette\PhpGenerator\PhpFile;
use InvalidArgumentException;

class ModelGenerator extends AbstractModelGenerator
{
    private string $baseFolder;
    private string $appFolder;
    private string $appNamespace;
    private string $modelsNamespace;
    private string $policiesNamespace;
    private string $generatedNamespace;
    private string $apiResourcesNamespace;
    private string $apiControllersNamespace;
    private string $generatedFolder;
    private string $migrationsPath;
    /**
     * @var OutputStyle
     */
    private OutputStyle $output;
    private Dumper $dumper;

    public function __construct(array $params, ?OutputStyle $output = null)
    {
        $this->modelsNamespace = config('jinn.models_namespace');
        $this->policiesNamespace = config('jinn.policies_namespace');
        $this->generatedFolder = config('jinn.generated_folder');
        $this->generatedNamespace = config('jinn.generated_namespace');
        $this->apiResourcesNamespace = config('jinn.api_resources_namespace');
        $this->apiControllersNamespace = config('jinn.api_controllers_namespace');

        $this->baseFolder = $params['baseFolder'];
        $this->appFolder = $params['appFolder'];
        $this->appNamespace = $params['appNamespace'];
        $this->migrationsPath = $params['migrationsPath'];

        $this->output = $output;
        $this->dumper = new Dumper();
    }

    protected function writeLine(string $line)
    {
        if ($this->output) $this->output->writeln($line);
    }

    private function name(...$parts) {
        return implode('\\', $parts);
    }

    private function nameToPath($baseFolder, $baseNamespace, $name) {
        $name = str_replace($baseNamespace, $baseFolder, $name);
        return str_replace('\\', '/', $name) . '.php';
    }

    private function generateClass(string $name, string $namespace, callable $classGenerator)
    {
        if (!$this->baseFolder) throw new LogicException('Base folder not defined');

        $classNamespace = $this->name($this->appNamespace, $namespace);
        $genNamespace = $this->name($this->generatedNamespace, $namespace);
        $className = $name;
        $genName = 'Base' . $name;
        $genFullName = $this->name($genNamespace, $genName);

        $genFile = new PhpFile();
        $genFile->addComment("Generated by Jinn. Do not edit.");
        $genNamespace = $genFile->addNamespace($genNamespace);
        $genClass = $genNamespace->addClass($genName);
        $genClass->setAbstract(true);

        $classGenerator($genClass, $classNamespace);

        PhpFileWriter::writePhpFile($this->nameToPath($this->generatedFolder, $this->generatedNamespace, $genFullName), $genFile);
        $this->writeLine("Generated class\t<info>$genName</info>");

        $classFilename = $this->nameToPath($this->appFolder, $this->appNamespace, $this->name($classNamespace, $className));
        if (!file_exists($classFilename)) {
            $classFile = new PhpFile();
            $classNamespace = $classFile->addNamespace($classNamespace);
            $classNamespace->addUse($genFullName);
            $class = $classNamespace->addClass($className);
            $class->setExtends($genFullName);

            PhpFileWriter::writePhpFile($classFilename, $classFile);
            $this->writeLine("Generated class\t<info>{$className}</info>");
        } else {
            $this->writeLine("Skipped class\t<info>{$className}</info>");
        }
    }

    protected function generateModel(Entity $entity): void
    {
        $this->generateClass($entity->name, $this->modelsNamespace,
            function(ClassType $genClass, $modelNamespace) use($entity) {
                $genClass->setExtends($entity->extends ?? 'Illuminate\Database\Eloquent\Model');
                foreach ($entity->implements as $implement) {
                    $genClass->addImplement($implement);
                }
                foreach ($entity->traits as $trait) {
                    $genClass->addTrait($trait);
                }

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
                    $code .= "\\$modelNamespace\\{$relation->entityName}::class";
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
        $this->generateClass($entity->name . 'Policy', $this->policiesNamespace,
            function(ClassType $genClass, $namespace) use ($entity, $policies) {
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

    protected function generateApiController(ApiController $apiController): string
    {
        $routes = '';

        $policies = [];

        $this->generateClass($apiController->name() . 'Controller', $this->apiControllersNamespace,
            function(ClassType $genClass, $namespace) use ($apiController, &$routes, &$policies) {
                $modelClass = $this->name($this->appNamespace, $this->modelsNamespace, $apiController->name());

                $routesRoot = Str::plural(Str::snake($apiController->name()));

                $genClass->setExtends('App\Http\Controllers\Controller');

                foreach ($apiController->methods() as $apiMethod) {
                    $method = $genClass->addMethod($apiMethod->name);

                    $param = $method->addParameter('request');
                    $param->setType('Illuminate\Http\Request');

                    $entityRoute = '';
                    $entityParamName = '';
                    if (!in_array($apiMethod->type, [ApiMethod::LIST, ApiMethod::CREATE]) || $apiMethod->relation) {
                        $entityParamName = Str::camel($apiController->name());
                        $entityRoute = '/{' . $entityParamName . '}';

                        $param = $method->addParameter($entityParamName);
                        $param->setType($modelClass);
                    }

                    $body = '';
                    if ($apiMethod->policy) {
                        $policies[] = $apiMethod->policy;
                        $body .= "\$this->authorize('{$apiMethod->name}', " .
                            ($entityParamName ? "\$$entityParamName" : "\\$modelClass::class") . ");\n";
                    }

                    $route = $routesRoot;

                    switch ($apiMethod->type) {
                        case ApiMethod::LIST:
                            if ($apiMethod->relation) {
                                $queryMethodName = 'get' . ucfirst($apiMethod->relation) . 'Query';
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

                            $body .= "return \$this->{$queryMethodName}($queryMethodParameters)->get();\n";
                            $routeMethod = 'get';
                            break;
                        case ApiMethod::GET:
                            $body .= "return \$$entityParamName;\n";
                            $routeMethod = 'get';
                            break;
                        case ApiMethod::CREATE:
                            $body .= "\\$modelClass::create(\$request->all());\n";
                            $routeMethod = 'post';
                            break;
                        case ApiMethod::UPDATE:
                            $body .= "\${$entityParamName}->update(\$request->all());\n";
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

                    $routes .= "\tRoute::$routeMethod('$route', [\\" . $this->name($namespace, $apiController->name() . 'Controller') . "::class, '{$apiMethod->name}']);\n";
                }
            }
        );

        if ($policies)
            $this->generatePolicy($apiController->entity, $policies);

        return $routes;

//        $this->generateClass($apiController->name() . 'Resource', $this->apiResourcesNamespace,
//            function(ClassType $genClass, $namespace) use ($apiController) {
//                $genClass->setExtends('Illuminate\Http\Resources\Json\JsonResource');
//
//                $method = $genClass->addMethod('toArray');
//                $method->addParameter('request');
//
//                $body = "return [\n";
//            }
//        );
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
