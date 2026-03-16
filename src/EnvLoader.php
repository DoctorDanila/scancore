<?php
namespace Scancore;

class EnvLoader
{
    /**
     * Загружает переменные окружения из файла .env в корне проекта.
     *
     * @param string $rootDir Корневая директория проекта.
     * @return array Ассоциативный массив переменных (ключ => значение).
     */
    public static function load(string $rootDir): array
    {
        $envFile = rtrim($rootDir, '/') . '/.env';
        if (!file_exists($envFile)) {
            return [];
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $vars = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                $vars[$key] = $value;
            }
        }

        return $vars;
    }
}