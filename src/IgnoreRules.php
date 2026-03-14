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

    private function parsePattern(string $pattern): array
    {
        $rootOnly = false;
        $dirOnly = false;

        // Шаблон, начинающийся с / — применяется только в корне
        if (strpos($pattern, '/') === 0) {
            $rootOnly = true;
            $pattern = substr($pattern, 1);
        }

        // Шаблон, заканчивающийся на / — применяется только к директориям
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
        // Экранируем специальные символы регулярных выражений
        $regex = preg_quote($pattern, '#');

        // Замена ** на .*? (любая последовательность, включая слеши)
        $regex = str_replace('\\*\\*', '.*?', $regex);

        // Замена * на [^/]* (любая последовательность, кроме слеша)
        $regex = str_replace('\\*', '[^/]*', $regex);

        // Замена ? на [^/] (один любой символ, кроме слеша)
        $regex = str_replace('\\?', '[^/]', $regex);

        // Если шаблон не корневой и не содержит слешей, добавляем **/ в начало,
        // чтобы он соответствовал любому уровню вложенности (например, .idea везде)
        if (!$rootOnly && strpos($pattern, '/') === false) {
            $regex = '(.*?/)?' . $regex;
        }

        return '#^' . $regex . '$#';
    }

    public function isIgnored(string $relativePath, bool $isDir): bool
    {
        foreach ($this->patterns as $p) {
            // Если правило только для директорий, а текущий элемент — файл, пропускаем
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