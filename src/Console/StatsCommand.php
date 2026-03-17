<?php
namespace Scancore\Console;

use Scancore\Scanner;
use Scancore\Stats\Collector;
use Scancore\Stats\DependencyAnalyzer;
use Scancore\Stats\HtmlGenerator;
use Scancore\Stats\LlmGenerator;
use Scancore\IgnoreOptionHandler;
use Scancore\Filter\FilterManager;
use Scancore\Filter\ClassFilter;
use Scancore\Filter\PatternFilter;
use Scancore\Filter\TypeFilter;
use Scancore\Filter\ImplementsFilter;
use Scancore\Filter\ExtendsFilter;
use Scancore\Filter\UseFilter;

class StatsCommand implements ICommand
{
    public function execute(Input $input): void
    {
        $args = $input->getArguments();
        $baseName = $args[0] ?? 'stats';
        if ($baseName === '.') {
            $baseName = 'stats';
        }
        $baseName = preg_replace('/\.html?$/', '', $baseName);

        $filterPath = isset($args[1]) ? trim(str_replace('\\', '/', $args[1]), '/') : '';

        $root = getcwd();

        $ignoreHandler = new IgnoreOptionHandler($input, 'stats', $root);
        $additionalPatterns = $ignoreHandler->getPatterns();

        $scanner = new Scanner($root, $additionalPatterns);
        $paths = $scanner->scan();

        if ($filterPath !== '') {
            $paths = array_filter($paths, function ($p) use ($filterPath) {
                return strpos($p, $filterPath . '/') === 0 || $p === $filterPath;
            });
        }

        // Сначала получаем зависимости для всех путей (нужны для фильтров impl,ext,use)
        $dependencyAnalyzer = new DependencyAnalyzer($root, $paths);
        $dependencies = $dependencyAnalyzer->analyze();
        $classMap = $dependencyAnalyzer->getClassMap(); // метод нужно добавить

        // Создаём менеджер фильтров
        $filterManager = new FilterManager();

        // Добавляем фильтры из опций
        $this->addFiltersFromInput($input, $filterManager, $classMap);

        // Применяем фильтры к путям
        $context = ['classMap' => $classMap];
        $filteredPaths = $filterManager->apply($paths, $root, $context);

        // Фильтруем зависимости, оставляя только файлы из отфильтрованного списка
        $filteredDependents = $filterManager->filterDependencies($dependencies['dependents'], $filteredPaths);

        // Перестраиваем зависимости (инвертированный массив) из отфильтрованных данных
        $filteredDependencies = [];
        foreach ($filteredDependents as $from => $toList) {
            foreach ($toList as $dep) {
                $to = $dep['file'];
                $filteredDependencies[$to][] = $from;
            }
        }
        uasort($filteredDependencies, fn($a, $b) => count($b) <=> count($a));

        $filteredDeps = [
            'dependents' => $filteredDependents,
            'dependencies' => $filteredDependencies,
        ];

        // Собираем статистику только по отфильтрованным файлам
        $collector = new Collector($root, $filteredPaths);
        $stats = $collector->collect();

        // Генерация HTML-отчёта (передаём отфильтрованные пути для дерева)
        $htmlGenerator = new HtmlGenerator($stats, $filteredDeps, $filteredPaths);
        $html = $htmlGenerator->generate();
        file_put_contents($root . '/' . $baseName . '.html', $html);

        // Генерация отчёта для LLM
        $llmGenerator = new LlmGenerator($stats, $filteredDeps);
        $llmData = $llmGenerator->generate();
        file_put_contents($root . '/' . $baseName . '_llm.txt', $llmData);

        echo "Статистика сохранена в {$baseName}.html и {$baseName}_llm.txt\n";
    }

    private function addFiltersFromInput(Input $input, FilterManager $fm, array $classMap): void
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

        // --impl
        $implValues = $this->expandOptionValues($input->getOption('impl'));
        if (!empty($implValues)) {
            $fm->addFilter(new ImplementsFilter($implValues));
        }

        // --ext
        $extValues = $this->expandOptionValues($input->getOption('ext'));
        if (!empty($extValues)) {
            $fm->addFilter(new ExtendsFilter($extValues));
        }

        // --use
        $useValues = $this->expandOptionValues($input->getOption('use'));
        if (!empty($useValues)) {
            $fm->addFilter(new UseFilter($useValues));
        }
    }

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