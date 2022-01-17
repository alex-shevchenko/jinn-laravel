<?php


namespace Jinn\Laravel\Generator;


use Doctrine\DBAL\Exception as DBALException;
use Jinn\Definition\Models\Application;
use Jinn\Definition\Models\View;
use Jinn\Generator\AbstractEntityGenerator;
use Jinn\Definition\Models\Entity;
use Jinn\Generator\GeneratorFactory;
use Jinn\Generator\PhpFileWriter;
use Jinn\Generator\GeneratorConfig;

class EntityGenerator extends AbstractEntityGenerator
{
    private GeneratorFactory $factory;
    private GeneratorConfig $config;

    public function __construct(GeneratorFactory $factory, GeneratorConfig $config)
    {
        $this->factory = $factory;
        $this->config = $config;

        GeneratorFactory::setNamespace('Jinn\\Laravel\\Generator');
    }

    protected function generateModel(Entity $entity): void
    {
        $generator = $this->factory->get(GeneratorFactory::MODEL);
        $generator->generate($entity);
    }

    protected function generateApiControllers(Application $application): void
    {
        $routes = "<?php\n/**\n * Generated by Jinn. Do not edit.\n */\n\n";
        $routes .= "use Illuminate\Support\Facades\Route;\n\n";
        $routes .= "return function() {\n";

        $generator = $this->factory->get(GeneratorFactory::API_CONTROLLER);

        foreach ($application->apiControllers() as $apiController) {
            $routes .= $generator->generate($apiController);
        }

        $routes .= "};\n";

        PhpFileWriter::writeFile($this->config->generatedFolder . '/routes/api.php', $routes);
        $this->config->output("Generated file\t<info>routes/api.php</info>");
    }

    /**
     * @param array $entities
     * @throws DBALException
     */
    protected function generateMigrations(array $entities): void
    {
        $creator = new MigrationCreator();

        foreach ($entities as $entity) {
            $filename = $creator->createStructureMigration($entity, $this->config->migrationsPath);
            if ($filename) ($this->config->output)("Generated migration <info>$filename</info>");

        }
        foreach ($entities as $entity) {
            $filename = $creator->createForeignKeysMigration($entity, $this->config->migrationsPath);
            if ($filename) ($this->config->output)("Generated migration <info>$filename</info>");
        }
    }

    protected function generateView(Entity $entity, View $view): void
    {
        $generator = $this->factory->get(GeneratorFactory::VIEW);
        $generator->generate($view, $entity);
    }
}
