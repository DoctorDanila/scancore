<?php
namespace Scancore\Console;

use Scancore\Scanner;
use Scancore\IgnoreOptionHandler;
use Scancore\Filter\FilterManager;
use Scancore\Filter\ClassFilter;
use Scancore\Filter\PatternFilter;
use Scancore\Filter\TypeFilter;

class DumpCommand implements ICommand
{
    public function execute(Input $input): void
    {
        $args = $input->getArguments();
        $outputFile = $args[0] ?? 'scancore_output.txt';
        if ($outputFile === '.') {
            $outputFile = 'scancore_output.txt';
        }
        $filterPath = isset($args[1]) ? trim(str_replace('\\', '/', $args[1]), '/') : '';
        $root = getcwd();

        $ignoreHandler = new IgnoreOptionHandler($input, 'dump', $root);
        $additionalPatterns = $ignoreHandler->getPatterns();

        $scanner = new Scanner($root, $additionalPatterns);
        $paths = $scanner->scan(); // все пути (файлы и папки)

        // Фильтрация по подпапке
        if ($filterPath !== '') {
            $paths = array_filter($paths, function ($p) use ($filterPath) {
                return strpos($p, $filterPath . '/') === 0 || $p === $filterPath;
            });
        }

        // Создаём менеджер фильтров
        $filterManager = new FilterManager();

        // Обрабатываем опции фильтров
        $this->addFiltersFromInput($input, $filterManager, $root);

        // Применяем фильтры
        if ($filterManager->hasFilters()) {
            // Для dump нам не нужен контекст classMap, передаём пустой массив
            $paths = $filterManager->apply($paths, $root);
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

    private function addFiltersFromInput(Input $input, FilterManager $fm, string $root): void
    {
        // --class
        $classValues = $this->expandOptionValues($input->getOption('class'));
        if (!empty($classValues)) {
            $fm->addFilter(new ClassFilter($classValues));
        }

        // --pattern
        $patternValues = $this->expandOptionValues($input->getOption('pattern'));
        if (!empty($patternValues)) {
            $fm->addFilter(new PatternFilter($patternValues));
        }

        // --type
        $typeValues = $this->expandOptionValues($input->getOption('type'));
        if (!empty($typeValues)) {
            $fm->addFilter(new TypeFilter($typeValues));
        }

        // Предупреждения для неподдерживаемых фильтров
        $unsupported = ['impl', 'ext', 'use'];
        foreach ($unsupported as $opt) {
            if ($input->hasOption($opt)) {
                fwrite(STDERR, "Warning: --$opt filter is not supported in dump command (ignored).\n");
            }
        }
    }

    /**
     * Преобразует значение опции в массив, разбивая по запятым.
     * Если передано несколько раз, объединяет все значения.
     */
    private function expandOptionValues(array $values): array
    {
        $result = [];
        foreach ($values as $v) {
            $parts = explode(',', $v);
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $result[] = $part;
                }
            }
        }
        return $result;
    }
}