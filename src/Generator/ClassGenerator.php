<?php


namespace Generator;


use Jinn\Definition\Models\Entity;
use Jinn\Generator\PhpFileWriter;
use Nette\PhpGenerator\PhpFile;

class ClassGenerator
{
    private function generateClass(Entity $entity, callable $classGenerator, string $namespace, string $suffix = '')
    {
        if (!$this->baseFolder) throw new LogicException('Base folder not defined');

        $classNamespace = $this->name($this->appNamespace, $namespace);
        $genNamespace = $this->name($this->generatedNamespace, $namespace);
        $className = $entity->name . $suffix;
        $genName = 'Base' . $entity->name . $suffix;
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
}