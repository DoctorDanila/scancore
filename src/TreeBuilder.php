<?php
namespace Scancore;

class TreeBuilder
{
    /**
     * Строит дерево из списка путей.
     */
    public function buildTree(array $paths, ?string $prefix = null): array
    {
        if ($prefix !== null) {
            $prefix = rtrim($prefix, '/');
            // Оставляем только пути, начинающиеся с префикса
            $paths = array_filter($paths, function ($p) use ($prefix) {
                return strpos($p, $prefix) === 0;
            });
            // Убираем префикс из начала путей
            $paths = array_map(function ($p) use ($prefix) {
                $relative = substr($p, strlen($prefix));
                return ltrim($relative, '/');
            }, $paths);
            // Убираем пустые строки (сам префикс)
            $paths = array_filter($paths);
        }

        $tree = [];
        foreach ($paths as $path) {
            $path = str_replace('\\', '/', $path);
            $parts = explode('/', $path);
            $current = &$tree;
            foreach ($parts as $part) {
                if (!isset($current[$part])) {
                    $current[$part] = [];
                }
                $current = &$current[$part];
            }
        }

        // Помечаем файлы (узлы без потомков) как null
        array_walk_recursive($tree, function (&$value, $key) {
            if (is_array($value) && empty($value)) {
                $value = null;
            }
        });

        return $tree;
    }
}