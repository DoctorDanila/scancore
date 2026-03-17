<?php
namespace Scancore\Stats;

if (!defined('T_NAME_QUALIFIED')) {
    define('T_NAME_QUALIFIED', 317);
}
if (!defined('T_NAME_FULLY_QUALIFIED')) {
    define('T_NAME_FULLY_QUALIFIED', 318);
}
if (!defined('T_NAME_RELATIVE')) {
    define('T_NAME_RELATIVE', 319);
}

class DependencyAnalyzer
{
    private const DEBUG = false;
    private string $root;
    private array $paths;
    private array $classMap = [];
    private array $fileUses = [];
    private array $fileNamespace = [];

    public function __construct(string $root, array $paths)
    {
        $this->root = rtrim($root, '/') . '/';
        $this->paths = $paths;
    }

    /**
     * Возвращает карту классов: полное имя класса => относительный путь файла.
     */
    public function getClassMap(): array
    {
        return $this->classMap;
    }

    public function analyze(): array
    {
        $phpFiles = array_filter($this->paths, fn($p) => pathinfo($p, PATHINFO_EXTENSION) === 'php');

        foreach ($phpFiles as $file) {
            $this->extractDefinitions($file);
        }

        $dependentsWithTypes = [];

        foreach ($phpFiles as $file) {
            $deps = $this->extractDependenciesWithTypes($file);
            $dependentsWithTypes[$file] = $deps;
        }

        $dependencies = [];
        foreach ($dependentsWithTypes as $from => $toList) {
            foreach ($toList as $dep) {
                $to = $dep['file'];
                $dependencies[$to][] = $from;
            }
        }

        uasort($dependencies, fn($a, $b) => count($b) <=> count($a));

        return [
            'dependents' => $dependentsWithTypes,
            'dependencies' => $dependencies,
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
                        $i++;
                        $this->skipWhitespaceAndComments($tokens, $i);
                        $ns = '';
                        $startIdx = $i;
                        while (isset($tokens[$i]) && is_array($tokens[$i]) &&
                            in_array($tokens[$i][0], [T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE])) {
                            $ns .= $tokens[$i][1];
                            $i++;
                        }
                        $this->debugLog("Namespace token after T_NAMESPACE: " . ($tokens[$startIdx][0] ?? 'none') . " value: " . ($tokens[$startIdx][1] ?? '') . " | full ns: '$ns'");
                        $namespace = $ns;
                        break;

                    case T_USE:
                        $this->parseUseStatement($tokens, $i, $uses, $relativeFile);
                        break;

                    case T_CLASS:
                    case T_INTERFACE:
                    case T_TRAIT:
                        $i++;
                        $this->skipWhitespace($tokens, $i);
                        if (isset($tokens[$i]) && is_array($tokens[$i]) && $tokens[$i][0] === T_STRING) {
                            $className = $tokens[$i][1];
                            $fullClass = $namespace ? $namespace . '\\' . $className : $className;
                            $this->classMap[$fullClass] = $relativeFile;
                            $this->debugLog("Class defined: $fullClass in file $relativeFile");
                        }
                        break;
                }
            }
            $i++;
        }

        $this->fileUses[$relativeFile] = $uses;
        $this->fileNamespace[$relativeFile] = $namespace;
    }

    private function parseUseStatement(array $tokens, int &$i, array &$uses, string $currentFile): void
    {
        $i++; // пропускаем T_USE
        $this->skipWhitespace($tokens, $i);
        $useStr = '';
        while (isset($tokens[$i]) && !(is_string($tokens[$i]) && $tokens[$i] === ';')) {
            if (is_array($tokens[$i])) {
                if ($tokens[$i][0] === T_WHITESPACE) {
                    $useStr .= ' ';
                } else {
                    $useStr .= $tokens[$i][1];
                }
            } else {
                $useStr .= $tokens[$i];
            }
            $i++;
        }
        $i++; // пропускаем ';'

        $parts = explode(',', $useStr);
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') continue;
            if (stripos($part, ' as ') !== false) {
                list($original, $alias) = preg_split('/\s+as\s+/i', $part, 2);
                $original = trim($original);
                $alias = trim($alias);
            } else {
                $original = $part;
                $alias = $this->getBaseName($original);
            }
            $uses[$alias] = $original;
            $this->debugLog("Use in $currentFile: alias '$alias' => original '$original'");
        }
    }

    private function getBaseName(string $fullName): string
    {
        $parts = explode('\\', $fullName);
        return end($parts);
    }

    private function extractDependenciesWithTypes(string $relativeFile): array
    {
        $fullPath = $this->root . $relativeFile;
        $content = file_get_contents($fullPath);
        if ($content === false) {
            return [];
        }

        $tokens = token_get_all($content);
        $namespace = $this->fileNamespace[$relativeFile] ?? '';
        $uses = $this->fileUses[$relativeFile] ?? [];

        $deps = [];
        $i = 0;
        $count = count($tokens);

        while ($i < $count) {
            $token = $tokens[$i];
            if (is_array($token)) {
                switch ($token[0]) {
                    case T_EXTENDS:
                        $type = 'extends';
                        $i++;
                        $this->skipWhitespace($tokens, $i);
                        $className = $this->readClassName($tokens, $i);
                        if ($className) {
                            $fullClass = $this->resolveClassName($className, $namespace, $uses);
                            if (isset($this->classMap[$fullClass])) {
                                $deps[] = ['file' => $this->classMap[$fullClass], 'type' => $type];
                                $this->debugLog("Dependency in $relativeFile: $type $className resolved to {$this->classMap[$fullClass]}");
                            } else {
                                $this->debugLog("Dependency in $relativeFile: $type $className could not be resolved (full class: $fullClass)");
                            }
                        }
                        break;

                    case T_IMPLEMENTS:
                        $type = 'implements';
                        $i++;
                        $this->skipWhitespace($tokens, $i);
                        while (true) {
                            $className = $this->readClassName($tokens, $i);
                            if ($className) {
                                $fullClass = $this->resolveClassName($className, $namespace, $uses);
                                if (isset($this->classMap[$fullClass])) {
                                    $deps[] = ['file' => $this->classMap[$fullClass], 'type' => $type];
                                    $this->debugLog("Dependency in $relativeFile: $type $className resolved to {$this->classMap[$fullClass]}");
                                } else {
                                    $this->debugLog("Dependency in $relativeFile: $type $className could not be resolved (full class: $fullClass)");
                                }
                            }
                            $this->skipWhitespace($tokens, $i);
                            if (isset($tokens[$i]) && is_string($tokens[$i]) && $tokens[$i] === ',') {
                                $i++;
                                $this->skipWhitespace($tokens, $i);
                                continue;
                            }
                            break;
                        }
                        break;

                    case T_INCLUDE:
                    case T_INCLUDE_ONCE:
                    case T_REQUIRE:
                    case T_REQUIRE_ONCE:
                        $type = 'include';
                        $i++;
                        $this->skipWhitespace($tokens, $i);

                        // Собираем всё выражение до точки с запятой, учитывая вложенные скобки
                        $expr = '';
                        $depth = 0;
                        while (isset($tokens[$i]) && !(is_string($tokens[$i]) && $tokens[$i] === ';' && $depth === 0)) {
                            if (is_array($tokens[$i])) {
                                $tokenValue = $tokens[$i][1];
                            } else {
                                $tokenValue = $tokens[$i];
                                if ($tokenValue === '(') $depth++;
                                if ($tokenValue === ')') $depth--;
                            }
                            $expr .= $tokenValue;
                            $i++;
                        }
                        // Пропускаем точку с запятой
                        if (isset($tokens[$i]) && is_string($tokens[$i]) && $tokens[$i] === ';') {
                            $i++;
                        }

                        // Пытаемся вычислить путь
                        $includePath = $this->evaluateIncludePath($expr, $relativeFile);
                        if ($includePath) {
                            $this->debugLog("Evaluated include path: $includePath from expr: $expr");
                            $resolvedFile = $this->resolveIncludePath($includePath, $relativeFile);
                            if ($resolvedFile) {
                                $deps[] = ['file' => $resolvedFile, 'type' => $type];
                                $this->debugLog("  Resolved to: $resolvedFile");
                            } else {
                                $this->debugLog("  Could not resolve evaluated path: $includePath");
                            }
                        } else {
                            $this->debugLog("Could not evaluate include expression: $expr");
                        }

                        // Завершаем обработку этого case, пропуская стандартный инкремент $i в конце цикла
                        continue 2;

                    case T_NEW:
                    case T_INSTANCEOF:
                    case T_CATCH:
                        $type = 'usage';
                        $i++;
                        $this->skipWhitespace($tokens, $i);
                        $className = $this->readClassName($tokens, $i);
                        if ($className) {
                            $fullClass = $this->resolveClassName($className, $namespace, $uses);
                            if (isset($this->classMap[$fullClass])) {
                                $deps[] = ['file' => $this->classMap[$fullClass], 'type' => $type];
                                $this->debugLog("Dependency in $relativeFile: $type $className resolved to {$this->classMap[$fullClass]}");
                            } else {
                                $this->debugLog("Dependency in $relativeFile: $type $className could not be resolved (full class: $fullClass)");
                            }
                        }
                        break;

                    case T_DOUBLE_COLON:
                        $this->handleDoubleColon($tokens, $i, $namespace, $uses, $relativeFile, $deps);
                        break;
                }
            }
            $i++;
        }

        // Добавляем импорты
        foreach ($uses as $alias => $original) {
            if (isset($this->classMap[$original])) {
                $deps[] = ['file' => $this->classMap[$original], 'type' => 'import'];
                $this->debugLog("Import in $relativeFile: use $alias => $original resolved to {$this->classMap[$original]}");
            } else {
                $this->debugLog("Import in $relativeFile: use $alias => $original could not be resolved");
            }
        }

        // Убираем дубликаты
        $unique = [];
        foreach ($deps as $dep) {
            $key = $dep['file'] . '|' . $dep['type'];
            $unique[$key] = $dep;
        }
        return array_values($unique);
    }

    /**
     * Обрабатывает оператор T_DOUBLE_COLON (::) для определения класса слева.
     *
     * @param array  $tokens       Массив токенов
     * @param int    $i            Текущая позиция (указывает на T_DOUBLE_COLON)
     * @param string $namespace    Текущее пространство имён
     * @param array  $uses         Импорты (use)
     * @param string $relativeFile Текущий файл
     * @param array  $deps         Ссылка на массив зависимостей
     */
    private function handleDoubleColon(array $tokens, int &$i, string $namespace, array $uses, string $relativeFile, array &$deps): void
    {
        // 1. Определяем левую часть (класс)
        $leftIdx = $i - 1;
        // Пропускаем пробелы и комментарии слева
        while ($leftIdx >= 0 && isset($tokens[$leftIdx]) && is_array($tokens[$leftIdx]) && in_array($tokens[$leftIdx][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
            $leftIdx--;
        }

        $leftToken = $leftIdx >= 0 ? $tokens[$leftIdx] : null;
        $isClassToken = $leftToken && is_array($leftToken) && in_array($leftToken[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE]);

        // 2. Пропускаем сам оператор ::
        $i++;
        $this->skipWhitespace($tokens, $i);

        // 3. Читаем имя после :: (метод или свойство) - продвигаем указатель, но само имя не сохраняем
        $rightPart = $this->readClassName($tokens, $i); // функция продвигает $i до конца имени

        if ($isClassToken) {
            $className = $leftToken[1];
            // Разрешаем класс
            $fullClass = $this->resolveClassName($className, $namespace, $uses);
            if (isset($this->classMap[$fullClass])) {
                $deps[] = ['file' => $this->classMap[$fullClass], 'type' => 'usage'];
                $this->debugLog("Static call in $relativeFile: $className resolved to {$this->classMap[$fullClass]}");
            } else {
                $this->debugLog("Static call in $relativeFile: $className could not be resolved (full class: $fullClass)");
            }
        } else {
            // Это может быть self::, parent::, static:: или $obj:: — пропускаем
            $this->debugLog("Static call with non-class left side in $relativeFile, skipping");
        }
    }

    /**
     * Пытается вычислить путь для include из собранного выражения.
     *
     * @param string $expr         Выражение, как оно выглядит в коде (например, "__DIR__ . '/file.php'").
     * @param string $relativeFile Относительный путь файла, из которого вызывается include (не используется здесь, но может пригодиться).
     * @return string|null Вычисленный путь (относительный) или null, если не удалось.
     */
    private function evaluateIncludePath(string $expr, string $relativeFile): ?string
    {
        $expr = trim($expr);
        if ($expr === '') {
            return null;
        }

        // 1. Простая строка в кавычках
        if (preg_match('/^([\'"])(.*?)\1$/', $expr, $matches)) {
            return $matches[2];
        }

        // 2. Конструкция __DIR__ . 'путь'
        if (strpos($expr, '__DIR__') === 0) {
            $rest = substr($expr, strlen('__DIR__'));
            $rest = trim($rest);
            // Ожидаем конкатенацию через точку
            if (strpos($rest, '.') === 0) {
                $rest = substr($rest, 1);
                $rest = trim($rest);
                if (preg_match('/^([\'"])(.*?)\1$/', $rest, $matches)) {
                    $relativePath = $matches[2];
                    // Нормализуем слеши (заменяем обратные на прямые)
                    $relativePath = str_replace('\\', '/', $relativePath);
                    // Убираем возможный ведущий слеш для единообразия (resolveIncludePath добавит свой разделитель)
                    return ltrim($relativePath, '/');
                }
            }
            // Если после __DIR__ ничего нет, возвращаем пустую строку (текущая директория)
            if ($rest === '') {
                return '';
            }
            return null;
        }

        // 3. Возможно, просто строка с кавычками, содержащая пробелы (например, "path/file.php")
        $stripped = trim($expr, '\'"');
        if ($stripped !== $expr) {
            return str_replace('\\', '/', $stripped);
        }

        return null;
    }

    private function skipWhitespace(array $tokens, int &$i): void
    {
        while (isset($tokens[$i]) && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
            $i++;
        }
    }

    private function skipWhitespaceAndComments(array $tokens, int &$i): void
    {
        while (isset($tokens[$i]) && is_array($tokens[$i]) &&
            in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
            $i++;
        }
    }

    private function readClassName(array $tokens, int &$i): string
    {
        $name = '';
        while (isset($tokens[$i]) && is_array($tokens[$i]) &&
            in_array($tokens[$i][0], [T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE])) {
            $name .= $tokens[$i][1];
            $i++;
        }
        return $name;
    }

    private function resolveClassName(string $name, string $currentNs, array $uses): string
    {
        if ($name[0] === '\\') {
            return ltrim($name, '\\');
        }

        $parts = explode('\\', $name);
        $first = $parts[0];
        if (isset($uses[$first])) {
            array_shift($parts);
            return $uses[$first] . ($parts ? '\\' . implode('\\', $parts) : '');
        }

        return $currentNs ? $currentNs . '\\' . $name : $name;
    }

    private function resolveIncludePath(string $includePath, string $relativeFile): ?string
    {
        // Нормализуем разделители в includePath
        $includePath = str_replace('\\', '/', $includePath);
        $currentDir = dirname($this->root . $relativeFile);
        // Нормализуем currentDir и root до формата с прямыми слешами
        $currentDir = str_replace('\\', '/', $currentDir);
        $root = str_replace('\\', '/', rtrim($this->root, '/'));

        // Функция для проверки кандидата
        $check = function($candidate) use ($root) {
            $real = realpath($candidate);
            if ($real === false) {
                return null;
            }
            $real = str_replace('\\', '/', $real);
            // Проверяем, что путь начинается с корня (с учётом слеша)
            if (strpos($real, $root . '/') === 0 || $real === $root) {
                $relative = substr($real, strlen($root) + 1); // +1 для слэша
                if ($relative === false) $relative = '';
                // Убедимся, что относительный путь есть в списке отсканированных путей
                if (in_array($relative, $this->paths)) {
                    return $relative;
                }
            }
            return null;
        };

        // Собираем все кандидаты
        $candidates = [];

        // 1. Относительно текущей директории
        $candidates[] = $currentDir . '/' . $includePath;

        // 2. Относительно корня
        $candidates[] = $root . '/' . ltrim($includePath, '/');

        // 3. Если нет расширения, добавляем .php
        if (!preg_match('/\.php$/i', $includePath)) {
            $withPhp = $includePath . '.php';
            $candidates[] = $currentDir . '/' . $withPhp;
            $candidates[] = $root . '/' . ltrim($withPhp, '/');
        }

        // Логирование
        $this->debugLog("Checking include '$includePath' from file '$relativeFile'\n");
        $this->debugLog("  currentDir: $currentDir\n  root: $root\n");

        foreach ($candidates as $candidate) {
            $this->debugLog("    Candidate: $candidate\n");
            $result = $check($candidate);
            if ($result !== null) {
                $this->debugLog("      FOUND: $result\n");
                return $result;
            }
        }

        $this->debugLog("  Could not resolve\n");
        return null;
    }

    private function debugLog(string $message): void
    {
        if (self::DEBUG)
            file_put_contents('scancore_include_debug.log', $message . PHP_EOL, FILE_APPEND);
    }
}