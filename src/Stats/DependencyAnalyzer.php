<?php
namespace Scancore\Stats;

class DependencyAnalyzer
{
    private string $root;
    private array $paths;
    private array $classMap = [];       // полное имя класса => файл
    private array $fileUses = [];       // файл => массив импортов (alias => original)
    private array $fileNamespace = [];  // файл => namespace

    public function __construct(string $root, array $paths)
    {
        $this->root = rtrim($root, '/') . '/';
        $this->paths = $paths;
    }

    public function analyze(): array
    {
        $phpFiles = array_filter($this->paths, fn($p) => pathinfo($p, PATHINFO_EXTENSION) === 'php');

        // Первый проход: собираем классы и use
        foreach ($phpFiles as $file) {
            $this->extractDefinitions($file);
        }

        // Второй проход: строим зависимости
        $dependents = []; // файл => список файлов, от которых зависит
        foreach ($phpFiles as $file) {
            $deps = $this->extractDependencies($file);
            $dependents[$file] = $deps;
        }

        // Инвертируем: для каждого файла список тех, кто от него зависит
        $dependencies = [];
        foreach ($dependents as $from => $toList) {
            foreach ($toList as $to) {
                $dependencies[$to][] = $from;
            }
        }

        // Сортируем по количеству зависимых (от большего к меньшему)
        uasort($dependencies, fn($a, $b) => count($b) <=> count($a));

        return [
            'dependents' => $dependents,        // кто от кого зависит
            'dependencies' => $dependencies,    // на кого чаще всего ссылаются
        ];
    }

    private function extractDefinitions(string $relativeFile): void
    {
        $fullPath = $this->root . $relativeFile;
        $content = file_get_contents($fullPath);
        if ($content === false) {
            return;
        }

        $tokens = token_get_all($content);
        $namespace = '';
        $uses = [];
        $i = 0;
        $count = count($tokens);

        while ($i < $count) {
            $token = $tokens[$i];
            if (is_array($token)) {
                switch ($token[0]) {
                    case T_NAMESPACE:
                        $i += 2; // пропускаем whitespace
                        $ns = '';
                        while (isset($tokens[$i]) && is_array($tokens[$i]) && in_array($tokens[$i][0], [T_STRING, T_NS_SEPARATOR])) {
                            $ns .= $tokens[$i][1];
                            $i++;
                        }
                        $namespace = $ns;
                        break;

                    case T_USE:
                        // Парсим use (только глобальные, не внутри классов)
                        $i += 2; // пропускаем whitespace
                        $use = '';
                        while (isset($tokens[$i]) && is_array($tokens[$i]) && in_array($tokens[$i][0], [T_STRING, T_NS_SEPARATOR, T_AS])) {
                            $use .= $tokens[$i][1];
                            $i++;
                        }
                        // Упрощённо: ожидаем "use A\B;"
                        if (preg_match('/^([^;]+?)(?:\s+as\s+([^;]+))?/', $use, $matches)) {
                            $original = trim($matches[1]);
                            $alias = trim($matches[2] ?? (substr(strrchr($original, '\\'), 1) ?: $original));
                            $uses[$alias] = $original;
                        }
                        break;

                    case T_CLASS:
                    case T_INTERFACE:
                    case T_TRAIT:
                        // Ищем имя класса/интерфейса/трейта
                        $i += 2; // пропускаем пробел
                        if (isset($tokens[$i]) && is_array($tokens[$i]) && $tokens[$i][0] === T_STRING) {
                            $className = $tokens[$i][1];
                            $fullClass = $namespace ? $namespace . '\\' . $className : $className;
                            $this->classMap[$fullClass] = $relativeFile;
                        }
                        break;
                }
            }
            $i++;
        }

        $this->fileUses[$relativeFile] = $uses;
        $this->fileNamespace[$relativeFile] = $namespace;
    }

    private function extractDependencies(string $relativeFile): array
    {
        $fullPath = $this->root . $relativeFile;
        $content = file_get_contents($fullPath);
        if ($content === false) {
            return [];
        }

        $tokens = token_get_all($content);
        $uses = $this->fileUses[$relativeFile] ?? [];
        $namespace = $this->fileNamespace[$relativeFile] ?? '';

        $deps = [];
        $i = 0;
        $count = count($tokens);

        while ($i < $count) {
            $token = $tokens[$i];
            if (is_array($token)) {
                switch ($token[0]) {
                    case T_EXTENDS:
                    case T_IMPLEMENTS:
                        // Переходим к следующему токену (после пробелов)
                        $i++;
                        // Пропускаем пробелы
                        while (isset($tokens[$i]) && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
                            $i++;
                        }
                        // Собираем имена классов/интерфейсов, разделённые запятыми
                        $classes = [];
                        $current = '';
                        while (isset($tokens[$i]) && !(is_string($tokens[$i]) && ($tokens[$i] === ';' || $tokens[$i] === '{'))) {
                            if (is_string($tokens[$i])) {
                                if ($tokens[$i] === ',') {
                                    // Конец одного имени
                                    if ($current !== '') {
                                        $classes[] = $current;
                                        $current = '';
                                    }
                                    $i++;
                                    // Пропускаем пробелы после запятой
                                    while (isset($tokens[$i]) && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
                                        $i++;
                                    }
                                    continue;
                                } else {
                                    // Другой символ (например, { или ;) выходим из цикла
                                    break;
                                }
                            } elseif (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_STRING, T_NS_SEPARATOR])) {
                                $current .= $tokens[$i][1];
                                $i++;
                            } else {
                                // Пропускаем остальное (например, пробелы уже обработаны отдельно)
                                $i++;
                            }
                        }
                        if ($current !== '') {
                            $classes[] = $current;
                        }
                        // Обрабатываем каждое имя
                        foreach ($classes as $class) {
                            $fullClass = $this->resolveClassName($class, $namespace, $uses);
                            if (isset($this->classMap[$fullClass])) {
                                $deps[] = $this->classMap[$fullClass];
                            }
                        }
                        // Продолжаем цикл, т.к. $i уже указывает на следующий токен после собранных имён
                        continue 2;

                    case T_USE:
                        // Для трейтов (use внутри класса) пока пропускаем, но можно добавить позже
                        break;
                }
            }
            $i++;
        }

        // Добавляем все импортированные классы как зависимости (они уже могут быть учтены выше,
        // но если класс используется только в type hints или new, то они не будут найдены,
        // поэтому добавляем все use как зависимости.
        foreach ($uses as $alias => $original) {
            if (isset($this->classMap[$original])) {
                $deps[] = $this->classMap[$original];
            }
        }

        return array_unique($deps);
    }

    private function resolveClassName(string $name, string $currentNs, array $uses): string
    {
        if ($name[0] === '\\') {
            // Абсолютное имя
            return ltrim($name, '\\');
        }

        // Относительное имя
        $parts = explode('\\', $name);
        $first = $parts[0];
        if (isset($uses[$first])) {
            // Замена алиаса
            array_shift($parts);
            return $uses[$first] . ($parts ? '\\' . implode('\\', $parts) : '');
        }

        // Иначе текущий namespace
        return $currentNs ? $currentNs . '\\' . $name : $name;
    }
}