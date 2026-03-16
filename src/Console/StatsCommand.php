<?php
namespace Scancore\Console;

use Scancore\Scanner;
use Scancore\Stats\Collector;
use Scancore\Stats\DependencyAnalyzer;
use Scancore\Stats\HtmlGenerator;
use Scancore\Stats\LlmGenerator;
use Scancore\IgnoreOptionHandler;

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

        // Основная статистика
        $collector = new Collector($root, $paths);
        $stats = $collector->collect();

        // Анализ зависимостей
        $dependencyAnalyzer = new DependencyAnalyzer($root, $paths);
        $dependencies = $dependencyAnalyzer->analyze();

        // Генерация HTML-отчёта
        $htmlGenerator = new HtmlGenerator($stats, $dependencies);
        $html = $htmlGenerator->generate();
        file_put_contents($root . '/' . $baseName . '.html', $html);

        // Генерация отчёта для LLM
        $llmGenerator = new LlmGenerator($stats, $dependencies);
        $llmData = $llmGenerator->generate();
        file_put_contents($root . '/' . $baseName . '_llm.txt', $llmData);

        echo "Статистика сохранена в {$baseName}.html и {$baseName}_llm.txt\n";
    }
}