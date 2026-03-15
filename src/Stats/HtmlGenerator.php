<?php
namespace Scancore\Stats;

class HtmlGenerator
{
    private array $stats;
    private array $dependencies;

    public function __construct(array $stats, array $dependencies)
    {
        $this->stats = $stats;
        $this->dependencies = $dependencies;
    }

    public function generate(): string
    {
        $totalFiles = $this->stats['total_files'];
        $totalLines = $this->stats['total_lines'];
        $filesByType = $this->stats['files_by_type'];
        $largestFiles = $this->stats['largest_files'];
        $activity = $this->stats['activity_by_day'];

        // Формируем таблицу активности по дням
        $activityRows = '';
        foreach ($activity as $date => $count) {
            $activityRows .= "<tr><td>{$date}</td><td>{$count}</td></tr>";
        }

        // Таблица самых больших файлов
        $largestRows = '';
        foreach ($largestFiles as $f) {
            $largestRows .= "<tr><td>{$f['path']}</td><td>{$f['size']} B</td><td>{$f['lines']}</td></tr>";
        }

        // Топ файлов, от которых больше всего зависят другие
        $mostDependedRows = '';
        $i = 0;
        foreach ($this->dependencies['dependencies'] ?? [] as $file => $dependents) {
            if ($i++ >= 20) break; // покажем топ-20
            $count = count($dependents);
            $mostDependedRows .= "<tr><td>{$file}</td><td>{$count}</td></tr>";
        }

        // Топ файлов, которые сами больше всего зависят от других
        $mostDependingRows = '';
        $dependingCounts = [];
        foreach ($this->dependencies['dependents'] ?? [] as $file => $dependsOn) {
            $dependingCounts[$file] = count($dependsOn);
        }
        arsort($dependingCounts);
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

        // Подготовка данных для графа зависимостей
        $nodes = [];
        $edges = [];
        $nodeIds = [];
        $nextId = 1;

        // Собираем все уникальные файлы, участвующие в зависимостях
        $allFiles = array_unique(array_merge(
            array_keys($this->dependencies['dependents'] ?? []),
            array_keys($this->dependencies['dependencies'] ?? [])
        ));

        foreach ($allFiles as $file) {
            $nodeIds[$file] = $nextId++;
            $nodes[] = [
                'id' => $nodeIds[$file],
                'label' => basename($file),
                'title' => $file, // полный путь во всплывающей подсказке
                'shape' => 'box',
            ];
        }

        // Создаём рёбра: если файл A зависит от B, то ребро A -> B
        foreach ($this->dependencies['dependents'] ?? [] as $from => $toList) {
            if (!isset($nodeIds[$from])) continue;
            foreach ($toList as $to) {
                if (!isset($nodeIds[$to])) continue;
                $edges[] = [
                    'from' => $nodeIds[$from],
                    'to' => $nodeIds[$to],
                    'arrows' => 'to',
                ];
            }
        }

        $nodesJson = json_encode($nodes, JSON_UNESCAPED_SLASHES);
        $edgesJson = json_encode($edges, JSON_UNESCAPED_SLASHES);

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Scancore — статистика проекта</title>
    <script type="text/javascript" src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
    <style>
        body { font-family: sans-serif; margin: 2em; background: #f5f5f5; }
        .container { max-width: 1200px; margin: auto; background: white; padding: 2em; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1, h2, h3 { color: #333; }
        table { border-collapse: collapse; width: 100%; margin: 1em 0; font-size: 0.9em; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
        #graph { width: 100%; height: 600px; border: 1px solid #ddd; margin: 20px 0; }
        .two-columns { display: flex; gap: 20px; }
        .two-columns > div { flex: 1; }
    </style>
</head>
<body>
<div class="container">
    <h1>Статистика проекта</h1>
    <p>Всего файлов: <strong>{$totalFiles}</strong></p>
    <p>Всего строк кода (приблизительно, без комментариев и пустых строк): <strong>{$totalLines}</strong></p>

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

    <h2>Граф зависимостей (PHP)</h2>
    <p>Направление стрелки: от файла к тому, от которого он зависит.</p>
    <div id="graph"></div>
</div>

<script>
    (function() {
        const nodes = new vis.DataSet({$nodesJson});
        const edges = new vis.DataSet({$edgesJson});

        const container = document.getElementById('graph');
        const data = { nodes, edges };
        const options = {
            layout: {
                hierarchical: false,
                improvedLayout: true,
            },
            edges: {
                smooth: { type: 'cubicBezier' },
                arrows: { to: { enabled: true, scaleFactor: 0.5 } },
            },
            physics: {
                enabled: true,
                solver: 'forceAtlas2Based',
                stabilization: { iterations: 100 },
            },
            interaction: {
                hover: true,
                tooltipDelay: 200,
                navigationButtons: true,
            },
            manipulation: false,
        };

        new vis.Network(container, data, options);
    })();
</script>
</body>
</html>
HTML;
    }
}