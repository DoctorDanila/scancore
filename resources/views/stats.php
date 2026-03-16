<?php
/**
 * @var int    $totalFiles
 * @var int    $totalLines
 * @var int    $averageLines
 * @var string $treeHtml
 * @var string $typesRows
 * @var string $largestRows
 * @var string $activityRows
 * @var string $mostDependedRows
 * @var string $mostDependingRows
 * @var string $importsFromRows
 * @var string $importsToRows
 * @var array  $graphAllData   ['nodes' => array, 'edges' => array]
 * @var array  $graphImportData ['nodes' => array, 'edges' => array]
 */
?><!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Scancore — статистика проекта</title>
    <!-- Подключаем Cytoscape и расширения через CDN с фиксированными версиями -->
    <script src="https://cdn.jsdelivr.net/npm/cytoscape@3.28.1/dist/cytoscape.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/cytoscape-cose-bilkent@2.1.0/cytoscape-cose-bilkent.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/cytoscape-expand-collapse@4.1.0/cytoscape-expand-collapse.min.js"></script>
    <style>
        body { font-family: sans-serif; margin: 2em; background: #f5f5f5; }
        .container { max-width: 1400px; margin: auto; background: white; padding: 2em; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1, h2, h3 { color: #333; }
        table { border-collapse: collapse; width: 100%; margin: 1em 0; font-size: 0.9em; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
        .graph-container { width: 100%; height: 600px; border: 1px solid #ddd; margin: 20px 0; }
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
        .filters { display: flex; gap: 20px; flex-wrap: wrap; margin: 10px 0; }
        .filter-group { display: flex; align-items: center; gap: 10px; }
        .footer { margin-top: 40px; text-align: center; color: #666; font-size: 0.9em; }
        .footer a { color: #333; text-decoration: none; }
        .footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="container">
    <h1>Статистика проекта</h1>
    <p>Всего файлов: <strong><?= $totalFiles ?></strong></p>
    <p>Всего строк кода (приблизительно, без комментариев и пустых строк): <strong><?= $totalLines ?></strong></p>
    <p>Среднее количество строк на файл: <strong><?= $averageLines ?></strong></p>

    <h2>Структура проекта</h2>
    <pre><?= $treeHtml ?></pre>

    <h2>Распределение по типам файлов</h2>
    <table>
        <tr><th>Расширение</th><th>Количество</th></tr>
        <?= $typesRows ?>
    </table>

    <div class="two-columns">
        <div>
            <h2>Самые большие файлы (топ-10)</h2>
            <table>
                <tr><th>Файл</th><th>Размер</th><th>Строк</th></tr>
                <?= $largestRows ?>
            </table>
        </div>
        <div>
            <h2>Активность по дням (последние изменения)</h2>
            <table>
                <tr><th>Дата</th><th>Файлов изменено</th></tr>
                <?= $activityRows ?>
            </table>
        </div>
    </div>

    <div class="two-columns">
        <div>
            <h2>Файлы, от которых чаще всего зависят другие</h2>
            <table>
                <tr><th>Файл</th><th>Количество зависимых</th></tr>
                <?= $mostDependedRows ?>
            </table>
        </div>
        <div>
            <h2>Файлы с наибольшим числом зависимостей</h2>
            <table>
                <tr><th>Файл</th><th>Сколько зависит от других</th></tr>
                <?= $mostDependingRows ?>
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
        <div class="filters">
            <div class="filter-group">
                <label><input type="checkbox" class="edge-filter" data-type="extends" checked> extends</label>
                <label><input type="checkbox" class="edge-filter" data-type="implements" checked> implements</label>
                <label><input type="checkbox" class="edge-filter" data-type="import" checked> import</label>
                <label><input type="checkbox" class="edge-filter" data-type="include" checked> include</label>
                <label><input type="checkbox" class="edge-filter" data-type="usage" checked> usage</label>
            </div>
            <div>
<!--                <button id="collapseAll">Свернуть все</button>-->
<!--                <button id="expandAll">Развернуть все</button>-->
            </div>
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
                    <?= $importsFromRows ?>
                </table>
            </div>
            <div>
                <h3>Файлы, которые чаще всего импортируются</h3>
                <table>
                    <tr><th>Файл</th><th>Количество импортирующих</th></tr>
                    <?= $importsToRows ?>
                </table>
            </div>
        </div>
        <div id="graphImportContainer" class="graph-container"></div>
    </div>

    <div class="footer">
        Разработано для компании <a href="https://thescript.agency" target="_blank">Script Agency</a>
    </div>
</div>

<script>
    // Глобальные переменные для хранения экземпляров графов и API сворачивания
    var cyAll = null;
    var cyImport = null;
    var collapseApi = null;

    // Регистрация расширений Cytoscape (выполняется один раз)
    (function registerExtensions() {
        if (typeof cytoscape === 'undefined') {
            console.error('Cytoscape не загружен');
            return;
        }
        // Регистрируем cose-bilkent, если ещё не зарегистрирован
        if (typeof cytoscapeCoseBilkent === 'function') {
            cytoscapeCoseBilkent(cytoscape);
            console.log('cytoscape-cose-bilkent зарегистрирован');
        } else {
            console.warn('cytoscapeCoseBilkent не найден');
        }

        // Регистрируем expand-collapse, если ещё не зарегистрирован
        if (typeof cytoscapeExpandCollapse === 'function' && !cytoscape.prototype.expandCollapse) {
            cytoscapeExpandCollapse(cytoscape);
            console.log('cytoscape-expand-collapse зарегистрирован');
        } else if (cytoscape.prototype.expandCollapse) {
            console.log('expandCollapse уже зарегистрирован');
        } else {
            console.warn('cytoscapeExpandCollapse не найден');
        }
    })();

    // Функция открытия вкладок
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

        // Создаём граф, если он ещё не создан, с небольшой задержкой для гарантии видимости контейнера
        if (tabName === 'graphAll' && !cyAll) {
            setTimeout(function() {
                cyAll = createCytoscape('graphAllContainer', <?= json_encode($graphAllData, JSON_UNESCAPED_SLASHES) ?>, true);
            }, 100);
        } else if (tabName === 'graphImport' && !cyImport) {
            setTimeout(function() {
                cyImport = createCytoscape('graphImportContainer', <?= json_encode($graphImportData, JSON_UNESCAPED_SLASHES) ?>, false);
            }, 100);
        }

        // Если графы уже созданы, обновляем их размер и подгоняем
        if (cyAll) {
            cyAll.resize();
            cyAll.fit();
        }
        if (cyImport) {
            cyImport.resize();
            cyImport.fit();
        }
    }

    // Функция создания графа
    function createCytoscape(containerId, elements, withFilters = true) {
        var container = document.getElementById(containerId);
        if (!container) {
            console.error('Контейнер не найден:', containerId);
            return null;
        }

        console.log('Создание графа в', containerId, 'размеры:', container.offsetWidth, 'x', container.offsetHeight);
        console.log('Узлов:', elements.nodes.length, 'Рёбер:', elements.edges.length);

        var cy = cytoscape({
            container: container,
            elements: elements,
            style: [
                {
                    selector: 'node[type="dir"]',
                    style: {
                        'shape': 'rectangle',
                        'background-color': '#E0E0E0',
                        'label': 'data(label)',
                        'font-size': '12px',
                        'text-valign': 'top',
                        'text-halign': 'center',
                        'border-width': 1,
                        'border-color': '#888'
                    }
                },
                {
                    selector: 'node[type="file"]',
                    style: {
                        'shape': 'ellipse',
                        'background-color': '#B0C4DE',
                        'label': 'data(label)',
                        'font-size': '10px',
                        'text-valign': 'center',
                        'text-halign': 'center',
                        'border-width': 1,
                        'border-color': '#333'
                    }
                },
                {
                    selector: 'edge[type="extends"]',
                    style: {
                        'line-color': '#3366CC',
                        'target-arrow-color': '#3366CC',
                        'width': 2,
                        'target-arrow-shape': 'triangle',
                        'curve-style': 'bezier'
                    }
                },
                {
                    selector: 'edge[type="implements"]',
                    style: {
                        'line-color': '#33CC66',
                        'target-arrow-color': '#33CC66',
                        'width': 2,
                        'target-arrow-shape': 'triangle',
                        'curve-style': 'bezier'
                    }
                },
                {
                    selector: 'edge[type="import"]',
                    style: {
                        'line-color': '#AAAAAA',
                        'target-arrow-color': '#AAAAAA',
                        'width': 1,
                        'target-arrow-shape': 'triangle',
                        'curve-style': 'bezier'
                    }
                },
                {
                    selector: 'edge[type="include"]',
                    style: {
                        'line-color': '#FF9900',
                        'target-arrow-color': '#FF9900',
                        'width': 2,
                        'target-arrow-shape': 'triangle',
                        'curve-style': 'bezier'
                    }
                },
                {
                    selector: 'edge[type="usage"]',
                    style: {
                        'line-color': '#AA66CC',
                        'target-arrow-color': '#AA66CC',
                        'width': 2,
                        'target-arrow-shape': 'triangle',
                        'curve-style': 'bezier'
                    }
                }
            ],
            layout: {
                name: 'cose-bilkent',
                animate: false,
                randomize: true,
                idealEdgeLength: 100,
                nodeRepulsion: 10000
            }
        });

        // Добавляем возможность сворачивания групп, если контейнер видим
        if (container.offsetWidth > 0 && container.offsetHeight > 0) {
            try {
                collapseApi = cy.expandCollapse({
                    fisheye: true,
                    animate: true
                });
                console.log('expandCollapse инициализирован');
            } catch (e) {
                console.warn('Ошибка инициализации expandCollapse:', e);
            }
        } else {
            console.warn('Контейнер имеет нулевой размер, сворачивание недоступно');
        }

        // Фильтры рёбер (только для графа всех зависимостей)
        if (withFilters) {
            var filters = document.querySelectorAll('.edge-filter');
            filters.forEach(function(cb) {
                cb.addEventListener('change', function() {
                    var type = this.dataset.type;
                    var visible = this.checked;
                    cy.edges('[type = "' + type + '"]').forEach(function(edge) {
                        edge.style('display', visible ? 'element' : 'none');
                    });
                });
            });
        }

        // Принудительно подгоняем граф после завершения layout
        cy.on('layoutstop', function() {
            cy.fit();
        });

        return cy;
    }

    // Кнопки свернуть/развернуть все
    document.getElementById('collapseAll')?.addEventListener('click', function() {
        if (collapseApi) {
            collapseApi.collapseAll();
        }
    });
    document.getElementById('expandAll')?.addEventListener('click', function() {
        if (collapseApi) {
            collapseApi.expandAll();
        }
    });

    // Открываем вкладку по умолчанию (это вызовет создание графа через setTimeout)
    document.getElementById("defaultOpen").click();
</script>
</body>
</html>