<?php


namespace Jinn\Laravel;


use Illuminate\Support\Facades\File;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;

class JinnFileWriter
{
    public static function writePhpFile(string $filename, PhpFile $file) {
        $folder = substr($filename, 0, strrpos($filename, '/'));
        File::ensureDirectoryExists($folder, 0755);

        $printer = new PsrPrinter();

        file_put_contents($filename, $printer->printFile($file));
    }
}
