<?php
namespace Scancore\Console;

use Scancore\Scanner;

class DumpCommand implements ICommand
{
    public function execute(array $args): void
    {
        $outputFile = $args[0] ?? 'scancore_output.txt';
        if ($outputFile === '.') {
            $outputFile = 'scancore_output.txt';
        }
        $filterPath = isset($args[1]) ? trim(str_replace('\\', '/', $args[1]), '/') : '';
        $root = getcwd();

        $scanner = new Scanner($root);
        $paths = $scanner->scan();

        if ($filterPath !== '') {
            $paths = array_filter($paths, function ($p) use ($filterPath) {
                return strpos($p, $filterPath . '/') === 0 || $p === $filterPath;
            });
        }

        // Оставляем только файлы
        $files = array_filter($paths, function ($p) use ($root) {
            return is_file($root . '/' . $p);
        });

        $handle = fopen($root . '/' . $outputFile, 'w');
        if (!$handle) {
            throw new \RuntimeException("Cannot create output file: $outputFile");
        }

        foreach ($files as $file) {
            fwrite($handle, "Файл: $file\n");
            $content = file_get_contents($root . '/' . $file);
            fwrite($handle, $content);
            fwrite($handle, "\n\n");
        }

        fclose($handle);
        echo "Dump written to $outputFile\n";
    }
}