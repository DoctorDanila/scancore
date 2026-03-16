<?php
namespace Scancore;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveCallbackFilterIterator;

class Scanner
{
    private string $rootDir;
    private IgnoreRules $ignore;

    /**
     * @param string $rootDir Корневая директория проекта.
     * @param array $additionalPatterns Временные паттерны для игнорирования (из командной строки).
     */
    public function __construct(string $rootDir, array $additionalPatterns = [])
    {
        $this->rootDir = rtrim($rootDir, '/') . '/';

        $initializer = new IgnoreInitializer();
        $initializer->ensure($this->rootDir, false, true);

        $this->ignore = new IgnoreRules($this->rootDir);
        if (!empty($additionalPatterns)) {
            $this->ignore->addPatterns($additionalPatterns);
        }
    }

    /**
     * Возвращает список относительных путей (файлы и директории) с учётом игнорирования.
     */
    public function scan(): array
    {
        $directory = new RecursiveDirectoryIterator($this->rootDir, RecursiveDirectoryIterator::SKIP_DOTS);
        $filter = new RecursiveCallbackFilterIterator($directory, function ($current, $key, $iterator) {
            $relativePath = $this->getRelativePath($current->getPathname());
            $isDir = $current->isDir();

            if ($this->ignore->isIgnored($relativePath, $isDir)) {
                return false;
            }

            // Для директорий разрешаем проход внутрь
            return true;
        });

        $iterator = new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::SELF_FIRST);

        $paths = [];
        foreach ($iterator as $item) {
            $relativePath = $this->getRelativePath($item->getPathname());
            $paths[] = $relativePath;
        }

        return $paths;
    }

    private function getRelativePath(string $fullPath): string
    {
        $relative = substr($fullPath, strlen($this->rootDir));
        return str_replace('\\', '/', $relative);
    }
}