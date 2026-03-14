<?php
namespace Scancore;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveCallbackFilterIterator;

class Scanner
{
    private string $rootDir;
    private IgnoreRules $ignore;

    public function __construct(string $rootDir)
    {
        $this->rootDir  = rtrim($rootDir, '/') . '/';
        $this->ignore   = new IgnoreRules($this->rootDir);
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