<?php
namespace Scancore\Stats;

use Scancore\TreeBuilder;
use Scancore\TreeRenderer;

class HtmlGenerator
{
    private array $stats;
    private array $dependencies;
    private array $allPaths;

    public function __construct(array $stats, array $dependencies, array $allPaths)
    {
        $this->stats = $stats;
        $this->dependencies = $dependencies;
        $this->allPaths = $allPaths;
    }

    public function generate(): string
    {
        $totalFiles = $this->stats['total_files'];
        $totalLines = $this->stats['total_lines'];
        $averageLines = $this->stats['average_lines'];
        $filesByType = $this->stats['files_by_type'];
        $largestFiles = $this->stats['largest_files'];
        $activity = $this->stats['activity_by_day'];

        // Дерево проекта
        $treeHtml = $this->renderTree();

        // Таблица активности по дням
        $activityRows = '';
        foreach ($activity as $date => $count) {
            $activityRows .= "<tr><td>{$date}</td><td>{$count}</td></tr>";
        }

        // Таблица самых больших файлов
        $largestRows = '';
        foreach ($largestFiles as $f) {
            $largestRows .= "<tr><td>{$f['path']}</td><td>{$f['size']} B</td><td>{$f['lines']}</td></tr>";
        }

        // Топ файлов, от которых чаще всего зависят другие (все типы)
        $mostDependedRows = '';
        $i = 0;
        foreach ($this->dependencies['dependencies'] ?? [] as $file => $dependents) {
            if ($i++ >= 20) break;
            $count = count($dependents);
            $mostDependedRows .= "<tr><td>{$file}</td><td>{$count}</td></tr>";
        }

        // Топ файлов, которые сами больше всего зависят от других (все типы)
        $dependingCounts = [];
        foreach ($this->dependencies['dependents'] ?? [] as $file => $depList) {
            $dependingCounts[$file] = count($depList);
        }
        arsort($dependingCounts);
        $mostDependingRows = '';
        $i = 0;
        foreach ($dependingCounts as $file => $count) {
            if ($i++ >= 20) break;
            $mostDependingRows .= "<tr><td>{$file}</td><td>{$count}</td></tr>";
        }

        // Типы файлов
        $typesRows = '';
        foreach ($filesByType as $ext => $count) {
            $typesRows .= "<tr><td>{$ext}</td><td>{$count}</td></tr>";
        }

        // ---- Блок зависимостей по импортам (use) ----
        $importsFrom = [];
        $importsTo = [];

        foreach ($this->dependencies['dependents'] ?? [] as $from => $depList) {
            foreach ($depList as $dep) {
                if ($dep['type'] === 'import') {
                    $to = $dep['file'];
                    $importsFrom[$from][] = $to;
                    $importsTo[$to][] = $from;
                }
            }
        }

        uasort($importsFrom, fn($a, $b) => count($b) <=> count($a));
        uasort($importsTo, fn($a, $b) => count($b) <=> count($a));

        $importsFromRows = '';
        $i = 0;
        foreach ($importsFrom as $file => $list) {
            if ($i++ >= 20) break;
            $importsFromRows .= "<tr><td>{$file}</td><td>" . count($list) . "</td></tr>";
        }

        $importsToRows = '';
        $i = 0;
        foreach ($importsTo as $file => $list) {
            if ($i++ >= 20) break;
            $importsToRows .= "<tr><td>{$file}</td><td>" . count($list) . "</td></tr>";
        }

        // Построение данных для Cytoscape
        $graphAllData = $this->buildGraphData($this->dependencies['dependents'] ?? []);
        $graphImportData = $this->buildGraphData($this->filterDepsByType($this->dependencies['dependents'] ?? [], 'import'));

        $totalNodes = count($graphAllData['nodes']);
        $isLargeGraph = $totalNodes > 100;

        // Подключаем шаблон
        ob_start();
        include __DIR__ . '/../../resources/views/stats.php';
        return ob_get_clean();
    }

    private function renderTree(): string
    {
        $builder = new TreeBuilder();
        $tree = $builder->buildTree($this->allPaths);
        $renderer = new TreeRenderer();
        return $renderer->render($tree);
    }

    /**
     * Строит данные для графа Cytoscape с учётом иерархии директорий.
     * Возвращает массив с ключами 'nodes' и 'edges'.
     */
    private function buildGraphData(array $dependents): array
    {
        $nodes = [];
        $edges = [];

        // Собираем все файлы, участвующие в зависимостях
        $allFiles = array_keys($dependents);
        foreach ($dependents as $from => $toList) {
            foreach ($toList as $dep) {
                $allFiles[] = $dep['file'];
            }
        }
        $allFiles = array_unique($allFiles);

        // Собираем все директории, встречающиеся в путях
        $dirs = [];
        foreach ($allFiles as $file) {
            $dir = dirname($file);
            while ($dir !== '.' && $dir !== '/') {
                $dirs[] = $dir;
                $dir = dirname($dir);
            }
        }
        $dirs = array_unique($dirs);

        // Создаём узлы для директорий
        $dirNodes = [];
        foreach ($dirs as $dir) {
            $id = 'dir:' . $dir;
            $label = basename($dir) ?: '/';
            $nodes[$id] = [
                'data' => [
                    'id' => $id,
                    'label' => $label,
                    'type' => 'dir',
                    'path' => $dir,
                ]
            ];
            $dirNodes[$dir] = $id;
        }

        // Создаём узлы для файлов и устанавливаем родительскую директорию
        $fileNodes = [];
        foreach ($allFiles as $file) {
            $id = 'file:' . $file;
            $parentDir = dirname($file);
            $parentId = $dirNodes[$parentDir] ?? null;

            $node = [
                'data' => [
                    'id' => $id,
                    'label' => basename($file),
                    'type' => 'file',
                    'path' => $file,
                ]
            ];
            if ($parentId) {
                $node['data']['parent'] = $parentId;
            }
            $nodes[$id] = $node;
            $fileNodes[$file] = $id;
        }

        // Создаём рёбра
        foreach ($dependents as $from => $toList) {
            if (!isset($fileNodes[$from])) continue;
            $fromId = $fileNodes[$from];
            foreach ($toList as $dep) {
                $to = $dep['file'];
                $type = $dep['type'];
                if (!isset($fileNodes[$to])) continue;
                $toId = $fileNodes[$to];
                $edges[] = [
                    'data' => [
                        'id' => uniqid('edge_'),
                        'source' => $fromId,
                        'target' => $toId,
                        'type' => $type,
                    ]
                ];
            }
        }

        return [
            'nodes' => array_values($nodes),
            'edges' => $edges,
        ];
    }

    private function filterDepsByType(array $dependents, string $type): array
    {
        $result = [];
        foreach ($dependents as $from => $list) {
            $filtered = array_filter($list, fn($dep) => $dep['type'] === $type);
            if (!empty($filtered)) {
                $result[$from] = array_values($filtered);
            }
        }
        return $result;
    }
}