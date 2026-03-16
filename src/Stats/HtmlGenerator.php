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
        $importsFrom = []; // кто импортирует
        $importsTo = [];   // кого импортируют

        foreach ($this->dependencies['dependents'] ?? [] as $from => $depList) {
            foreach ($depList as $dep) {
                if ($dep['type'] === 'import') {
                    $to = $dep['file'];
                    $importsFrom[$from][] = $to;
                    $importsTo[$to][] = $from;
                }
            }
        }

        // Сортируем по убыванию количества
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

        // Граф всех зависимостей с цветами
        $graphData = $this->buildGraphData($this->dependencies['dependents'] ?? []);
        // Граф только импортов
        $importGraphData = $this->buildGraphData($this->filterDepsByType($this->dependencies['dependents'] ?? [], 'import'));

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Scancore — статистика проекта</title>
    <script type="text/javascript" src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
    <style>
        body { font-family: sans-serif; margin: 2em; background: #f5f5f5; }
        .container { max-width: 1400px; margin: auto; background: white; padding: 2em; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1, h2, h3 { color: #333; }
        table { border-collapse: collapse; width: 100%; margin: 1em 0; font-size: 0.9em; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
        .graph-container { width: 100%; height: 500px; border: 1px solid #ddd; margin: 20px 0; }
        .two-columns { display: flex; gap: 20px; }
        .two-columns > div { flex: 1; }
        .legend { display: flex; gap: 20px; margin: 10px 0; flex-wrap: wrap; }
        .legend-item { display: flex; align-items: center; gap: 5px; }
        .legend-color { width: 20px; height: 20px; border-radius: 4px; }
        pre { background: #f8f8f8; padding: 1em; border-radius: 5px; overflow-x: auto; font-size: 0.8em; }
        .tab { overflow: hidden; border: 1px solid #ccc; background-color: #f1f1f1; }
        .tab button { background-color: inherit; float: left; border: none; outline: none; cursor: pointer; padding: 10px 16px; transition: 0.3s; }
        .tab button:hover { background-color: #ddd; }
        .tab button.active { background-color: #ccc; }
        .tabcontent { display: none; padding: 20px; border: 1px solid #ccc; border-top: none; }
    </style>
</head>
<body>
<div class="container">
    <h1>Статистика проекта</h1>
    <p>Всего файлов: <strong>{$totalFiles}</strong></p>
    <p>Всего строк кода (приблизительно, без комментариев и пустых строк): <strong>{$totalLines}</strong></p>
    <p>Среднее количество строк на файл: <strong>{$averageLines}</strong></p>

    <h2>Структура проекта</h2>
    <pre>{$treeHtml}</pre>

    <h2>Распределение по типам файлов</h2>
    <table>
        <tr><th>Расширение</th><th>Количество</th></tr>
        {$typesRows}
    </table>

    <div class="two-columns">
        <div>
            <h2>Самые большие файлы (топ-10)</h2>
            <table>
                <tr><th>Файл</th><th>Размер</th><th>Строк</th></tr>
                {$largestRows}
            </table>
        </div>
        <div>
            <h2>Активность по дням (последние изменения)</h2>
            <table>
                <tr><th>Дата</th><th>Файлов изменено</th></tr>
                {$activityRows}
            </table>
        </div>
    </div>

    <div class="two-columns">
        <div>
            <h2>Файлы, от которых чаще всего зависят другие</h2>
            <table>
                <tr><th>Файл</th><th>Количество зависимых</th></tr>
                {$mostDependedRows}
            </table>
        </div>
        <div>
            <h2>Файлы с наибольшим числом зависимостей</h2>
            <table>
                <tr><th>Файл</th><th>Сколько зависит от других</th></tr>
                {$mostDependingRows}
            </table>
        </div>
    </div>

    <div class="tab">
        <button class="tablinks" onclick="openTab(event, 'graphAll')" id="defaultOpen">Граф всех зависимостей</button>
        <button class="tablinks" onclick="openTab(event, 'graphImport')">Граф импортов (use)</button>
    </div>

    <div id="graphAll" class="tabcontent">
        <h2>Граф всех зависимостей</h2>
        <div class="legend">
            <div class="legend-item"><span class="legend-color" style="background:#3366CC;"></span> extends</div>
            <div class="legend-item"><span class="legend-color" style="background:#33CC66;"></span> implements</div>
            <div class="legend-item"><span class="legend-color" style="background:#AAAAAA;"></span> import (use)</div>
            <div class="legend-item"><span class="legend-color" style="background:#FF9900;"></span> include/require</div>
            <div class="legend-item"><span class="legend-color" style="background:#AA66CC;"></span> usage (new, ::, instanceof, catch)</div>
        </div>
        <div id="graphAllContainer" class="graph-container"></div>
    </div>

    <div id="graphImport" class="tabcontent">
        <h2>Граф импортов (use)</h2>
        <div class="two-columns">
            <div>
                <h3>Файлы, которые больше всего импортируют</h3>
                <table>
                    <tr><th>Файл</th><th>Количество импортов</th></tr>
                    {$importsFromRows}
                </table>
            </div>
            <div>
                <h3>Файлы, которые чаще всего импортируются</h3>
                <table>
                    <tr><th>Файл</th><th>Количество импортирующих</th></tr>
                    {$importsToRows}
                </table>
            </div>
        </div>
        <div id="graphImportContainer" class="graph-container"></div>
    </div>
</div>

<script>
    function openTab(evt, tabName) {
        var i, tabcontent, tablinks;
        tabcontent = document.getElementsByClassName("tabcontent");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }
        tablinks = document.getElementsByClassName("tablinks");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].className = tablinks[i].className.replace(" active", "");
        }
        document.getElementById(tabName).style.display = "block";
        evt.currentTarget.className += " active";
    }

    // Открываем вкладку по умолчанию
    document.getElementById("defaultOpen").click();

    (function() {
        // Граф всех зависимостей
        const nodesAll = new vis.DataSet({$graphData['nodes']});
        const edgesAll = new vis.DataSet({$graphData['edges']});
        const containerAll = document.getElementById('graphAllContainer');
        new vis.Network(containerAll, { nodes: nodesAll, edges: edgesAll }, {
            layout: { hierarchical: false, improvedLayout: true },
            edges: { smooth: { type: 'cubicBezier' }, arrows: { to: { enabled: true, scaleFactor: 0.5 } } },
            physics: { enabled: true, solver: 'forceAtlas2Based', stabilization: { iterations: 100 } },
            interaction: { hover: true, tooltipDelay: 200, navigationButtons: true }
        });

        // Граф импортов
        const nodesImport = new vis.DataSet({$importGraphData['nodes']});
        const edgesImport = new vis.DataSet({$importGraphData['edges']});
        const containerImport = document.getElementById('graphImportContainer');
        new vis.Network(containerImport, { nodes: nodesImport, edges: edgesImport }, {
            layout: { hierarchical: false, improvedLayout: true },
            edges: { smooth: { type: 'cubicBezier' }, arrows: { to: { enabled: true, scaleFactor: 0.5 } }, color: '#AAAAAA' },
            physics: { enabled: true, solver: 'forceAtlas2Based', stabilization: { iterations: 100 } },
            interaction: { hover: true, tooltipDelay: 200, navigationButtons: true }
        });
    })();
</script>
</body>
</html>
HTML;
    }

    private function renderTree(): string
    {
        $builder = new TreeBuilder();
        $tree = $builder->buildTree($this->allPaths);
        $renderer = new TreeRenderer();
        return $renderer->render($tree);
    }

    private function buildGraphData(array $dependents): array
    {
        $nodes = [];
        $edges = [];
        $nodeIds = [];
        $nextId = 1;

        // Все уникальные файлы, участвующие в зависимостях
        $allFiles = array_keys($dependents);
        foreach ($dependents as $from => $toList) {
            foreach ($toList as $dep) {
                $allFiles[] = $dep['file'];
            }
        }
        $allFiles = array_unique($allFiles);

        foreach ($allFiles as $file) {
            $nodeIds[$file] = $nextId++;
            $nodes[] = [
                'id' => $nodeIds[$file],
                'label' => basename($file),
                'title' => $file,
                'shape' => 'box',
            ];
        }

        // Цвета для разных типов
        $colorMap = [
            'extends'   => '#3366CC',
            'implements' => '#33CC66',
            'import'    => '#AAAAAA',
            'include'   => '#FF9900',
            'usage'     => '#AA66CC',
        ];

        foreach ($dependents as $from => $toList) {
            if (!isset($nodeIds[$from])) continue;
            foreach ($toList as $dep) {
                $to = $dep['file'];
                $type = $dep['type'];
                if (!isset($nodeIds[$to])) continue;
                $color = $colorMap[$type] ?? '#000000';
                $edges[] = [
                    'from' => $nodeIds[$from],
                    'to' => $nodeIds[$to],
                    'arrows' => 'to',
                    'color' => $color,
                    'title' => $type,
                ];
            }
        }

        return [
            'nodes' => json_encode($nodes, JSON_UNESCAPED_SLASHES),
            'edges' => json_encode($edges, JSON_UNESCAPED_SLASHES),
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