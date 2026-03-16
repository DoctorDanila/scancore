<?php
namespace Scancore;

class IgnoreRules
{
    private array $patterns = [];
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/') . '/';
        $this->load();
    }

    /**
     * Добавляет временные паттерны к существующим правилам.
     *
     * @param array $patterns Список строк-паттернов (как в .scancoreignore)
     */
    public function addPatterns(array $patterns): void
    {
        foreach ($patterns as $pattern) {
            $pattern = trim($pattern);
            if ($pattern === '' || $pattern[0] === '#') {
                continue;
            }
            $this->patterns[] = $this->parsePattern($pattern);
        }
    }

    private function load(): void
    {
        $file = $this->basePath . '.scancoreignore';
        if (!file_exists($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $this->patterns[] = $this->parsePattern($line);
        }
    }

    protected function parsePattern(string $pattern): array
    {
        $rootOnly = false;
        $dirOnly = false;

        // Шаблон, начинающийся с / - применяется только в корне
        if (strpos($pattern, '/') === 0) {
            $rootOnly = true;
            $pattern = substr($pattern, 1);
        }

        // Шаблон, заканчивающийся на / - применяется только к директориям
        if (substr($pattern, -1) === '/') {
            $dirOnly = true;
            $pattern = substr($pattern, 0, -1);
        }

        // Преобразование в регулярное выражение
        $regex = $this->patternToRegex($pattern, $rootOnly);

        return [
            'pattern' => $pattern,
            'regex'   => $regex,
            'rootOnly'=> $rootOnly,
            'dirOnly' => $dirOnly,
        ];
    }

    private function patternToRegex(string $pattern, bool $rootOnly): string
    {
        $regex = preg_quote($pattern, '#');
        // Замена ** на .*?
        $regex = str_replace('\\*\\*', '.*?', $regex);
        // Замена * на [^/]*
        $regex = str_replace('\\*', '[^/]*', $regex);
        // Замена ? на [^/]
        $regex = str_replace('\\?', '[^/]', $regex);

        if (!$rootOnly && strpos($pattern, '/') === false) {
            // Паттерн без слешей должен соответствовать любому пути, где он является целым компонентом
            $regex = '(^|/)' . $regex . '$';
        } else {
            $regex = '^' . $regex . '$';
        }
        return '#' . $regex . '#';
    }

    public function isIgnored(string $relativePath, bool $isDir): bool
    {
        foreach ($this->patterns as $p) {
            // Если правило только для директорий, а текущий элемент - файл, пропускаем
            if ($p['dirOnly'] && !$isDir) {
                continue;
            }

            if ($p['rootOnly']) {
                // Корневой шаблон: путь должен начинаться с pattern и после него либо конец, либо слеш
                if (strpos($relativePath, $p['pattern']) === 0) {
                    $rest = substr($relativePath, strlen($p['pattern']));
                    if ($rest === '' || $rest[0] === '/') {
                        return true;
                    }
                }
            } else {
                // Для некорневых шаблонов используем регулярное выражение
                if (preg_match($p['regex'], $relativePath)) {
                    return true;
                }
            }
        }
        return false;
    }
}